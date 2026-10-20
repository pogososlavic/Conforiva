"""Scoped recovery of exact-model LG manufacturer sheets from public mirrors."""
import json, urllib.request, hashlib, pathlib, datetime, io
from pypdf import PdfReader
from PIL import Image

BASE=pathlib.Path('storage/app/private/product-photo-alternatives-20261019/laundry')
SOURCES={
 'F14V13WHS':'https://gurdjian.fr/GV2/021051/F14V13WHS_FP.pdf',
 'F864V31WR':'https://media.but.fr/products/media/8806091699145/8806091699145_FP.pdf',
 'F964R35WR':'https://boulanger.scene7.com/is/content/Boulanger/8806084221254_f_0',
 'F964R71WRST':'https://media.adeo.com/mkp/f22b8e2c1d218c8cb8d1870119e6a92e/media.pdf',
 'F964R56MBRS':'https://www.lentrepotdiscount.fr/uploads/documents/fiche-f964r56mbrs-FR-392.pdf?1757125286=',
 'F964R35WRS':'https://boulanger.scene7.com/is/content/Boulanger/8806084220974_f_0',
 'F964R33WR':'https://media.but.fr/products/media/8806084221193/8806084221193_FP.pdf',
 'F14R33WHS':'https://media.but.fr/products/media/8806084133373/8806084133373_FP.pdf',
 'F964R33WRS':'https://www.procie.com/mediaprovider/index/index/media_type/notice/filename/F964R33WRS.pdf/',
 'LSWD100E-italian':'https://pim.freeshop.it/media/mmo_93095049_1654772364_0134_32284.pdf',
 'LSWD100E':'https://boulanger.scene7.com/is/content/Boulanger/8806091421395_f_0',
}

def main():
 BASE.mkdir(parents=True,exist_ok=True,mode=0o700)
 manifest=[{'reference':r,'source_url':u,'source_page':u,'publisher':'LG Electronics, document constructeur hébergé par distributeur','rights_basis':'Déclaration utilisateur HOMYA du 2026-10-07 DECISION-016 pour médias constructeur ; contrats non examinés, aucune licence ouverte revendiquée','retrieved_at':datetime.datetime.now(datetime.timezone.utc).isoformat(),'destination':str(BASE/r)} for r,u in SOURCES.items()]
 (BASE/'manifest-before-download.json').write_text(json.dumps(manifest,ensure_ascii=False,indent=2))
 results=[]
 for ref,url in SOURCES.items():
  dest=BASE/ref;dest.mkdir(exist_ok=True)
  try:
   if (dest/'source.pdf').exists():data=(dest/'source.pdf').read_bytes()
   else:
    req=urllib.request.Request(url,headers={'User-Agent':'HOMYA-Local-Recovery/1.0'})
    with urllib.request.urlopen(req,timeout=40) as res: data=res.read(15000001); status=res.status
   if not data.startswith(b'%PDF') or len(data)>15000000:raise ValueError('Expected PDF <15MB')
   (dest/'source.pdf').write_bytes(data)
   pdf=PdfReader(io.BytesIO(data)); text='\n'.join(p.extract_text() or '' for p in pdf.pages)
   (dest/'text.txt').write_text(text)
   if ref.replace('-italian','') not in text: raise ValueError('Exact reference absent in PDF text')
   images=[]
   for page_num,p in enumerate(pdf.pages,1):
    for i,img in enumerate(p.images):
     im=Image.open(io.BytesIO(img.data)); w,h=im.size
     if max(w,h)>=500:
      path=dest/f'page{page_num}-image{i}-{img.name}'
      path.write_bytes(img.data)
      images.append({'path':str(path),'dimensions':[w,h],'sha256':hashlib.sha256(img.data).hexdigest(),'bytes':len(img.data),'format':im.format,'page':page_num})
   item={'reference':ref,'source_url':url,'status':'candidates_extracted','pdf_sha256':hashlib.sha256(data).hexdigest(),'pdf_bytes':len(data),'reference_in_text':True,'images':images,'visual_review':'pending'}
  except Exception as e:item={'reference':ref,'source_url':url,'status':'blocked','error':str(e)}
  results.append(item); print(json.dumps(item),flush=True)
 (BASE/'extraction-results.json').write_text(json.dumps(results,ensure_ascii=False,indent=2))

if __name__=='__main__':main()
