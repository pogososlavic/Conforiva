#!/usr/bin/env python3
"""Recover only individually researched LG manufacturer documents, privately."""
import json, pathlib, urllib.request, hashlib, datetime
ROOT=pathlib.Path(__file__).resolve().parents[1]
OUT=ROOT/'storage/app/private/product-photo-alternatives-20261019/cooling'
SOURCES={
 'GBV3200DSW':('https://www.lentrepotdiscount.fr/refrigerateur-lg-gbv3200dsw/','https://www.lentrepotdiscount.fr/uploads/documents/fiche-gbv3200dsw-FR-666.pdf?1792435435'),
 'GLM71MCCSD':('https://www.boulanger.com/ref/1160268','https://boulanger.scene7.com/is/content/Boulanger/8806091257642_f_0'),
 'GFM61MCCSD':('https://www.boulanger.com/ref/1207947','https://boulanger.scene7.com/is/content/Boulanger/8806091257659_f_0'),
 'GBB72SWVDN':('https://media.adeo.com/mkp/f8964f325c630641aa572b80286a5f50/media.pdf','https://media.adeo.com/mkp/f8964f325c630641aa572b80286a5f50/media.pdf'),
 'GFT61SWCSE':('https://media.but.fr/products/media/8806091276001/8806091276001_FP.pdf','https://media.but.fr/products/media/8806091276001/8806091276001_FP.pdf'),
 'GLT71SWCSE':('https://www.boulanger.com/ref/1225542','https://boulanger.scene7.com/is/content/Boulanger/8806091275981_f_0'),
 'GFT61PZCSE':('https://www.primo-ideo.com/refrigerateur-1-porte-lg-gft61pzcse.htm','https://cdn.store-factory.com/www.primo-ideo.com/media/1.pdf'),
 'GMX945NS9F':('https://www.planetetechnologie.com/refrigerateur-congelateur/47945-lg-gmx945ns9f-8806091820235.html','https://www.planetetechnologie.com/index.php?controller=attachment&id_attachment=14458'),
}
def main():
 OUT.mkdir(parents=True,exist_ok=True,mode=0o700)
 overrides=json.loads((ROOT/'storage/app/catalogue/media-overrides.json').read_text())['products']
 rows=[]
 for reference,(page,url) in SOURCES.items():
  match=next(v for v in overrides.values() if v.get('reference')==reference)
  rows.append(dict(reference=reference,slug=match['slug'],source_page=page,source_document_url=url,source_publisher='Document commercial LG, hébergé par distributeur',rights_status=match['rights_status'],rights_scope=match['rights_scope'],rights_decision=match.get('rights_decision'),date=datetime.date.today().isoformat(),destination=str(OUT/(reference+'.pdf')),status='planned'))
 (OUT/'manifest-before-download.json').write_text(json.dumps(rows,indent=2,ensure_ascii=False))
 for row in rows:
  if row['reference']=='GMX945NS9F':
   row.update(status='blocked_403_previous_attempt');continue
  path=pathlib.Path(row['destination'])
  try:
   if not path.exists():
    with urllib.request.urlopen(urllib.request.Request(row['source_document_url'],headers={'User-Agent':'HOMYA-Local-Recovery/1.0'}),timeout=30) as r:
     body=r.read(15_000_001)
     if len(body)>15_000_000 or not body.startswith(b'%PDF'):raise ValueError('not PDF / too large')
     path.write_bytes(body)
   body=path.read_bytes()
   row.update(status='document_recovered',sha256=hashlib.sha256(body).hexdigest(),bytes=len(body))
  except Exception as e:row.update(status='download_failed',error=str(e))
  print(row['reference'],row['status'],row.get('error',''),flush=True)
 (OUT/'documents-results.json').write_text(json.dumps(rows,indent=2,ensure_ascii=False))
if __name__=='__main__':main()
