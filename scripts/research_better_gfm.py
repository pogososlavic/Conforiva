import json, hashlib, urllib.request, re, html
from pathlib import Path
from datetime import datetime, timezone

ROOT=Path(__file__).resolve().parents[1]
OUT=ROOT/'storage/app/private/photo-quality-upgrade-20261019/gfm'
OUT.mkdir(parents=True, exist_ok=True)

def get(url, filename):
    plan=OUT/'download-plan.json'
    rows=json.loads(plan.read_text()) if plan.exists() else []
    rows.append(dict(source_url=url, destination=str(OUT/filename), reference='GFM61MCCSD',ean='8806091257659',finish='NOIR CARBONE',owner='LG / publication distributeur Darty',rights='DECISION-016, déclaration utilisateur, contrats non audités',date=datetime.now(timezone.utc).isoformat()))
    plan.write_text(json.dumps(rows,indent=2,ensure_ascii=False)+'\n')
    req=urllib.request.Request(url,headers={'User-Agent':'HOMYA product-media recovery (model GFM61MCCSD)'})
    with urllib.request.urlopen(req,timeout=40) as r:
        data=r.read(15000000)
        info=dict(url=url,final_url=r.url,status=r.status,content_type=r.headers.get('Content-Type'),sha256=hashlib.sha256(data).hexdigest(),bytes=len(data),destination=str(OUT/filename))
    (OUT/filename).write_bytes(data)
    meta=OUT/'downloads.json'
    hist=json.loads(meta.read_text()) if meta.exists() else []
    hist.append(info);meta.write_text(json.dumps(hist,indent=2,ensure_ascii=False)+'\n')
    return data

if __name__=='__main__':
    import sys
    if sys.argv[1]=='page':
        data=get('https://martinique.darty-dom.com/p/lg-gfm61mccsd','darty-page.html').decode()
        urls=sorted(set(html.unescape(x) for x in re.findall(r'(?:https://martinique\.darty-dom\.com)?/thumbnails/[^\s"<>]+',data)))
        (OUT/'published-gallery-urls.json').write_text(json.dumps(urls,indent=2))
        print(json.dumps(urls,indent=2))
    elif sys.argv[1]=='image':
        from PIL import Image
        get(sys.argv[2],sys.argv[3])
        im=Image.open(OUT/sys.argv[3]);print(im.size,im.format)
    elif sys.argv[1]=='finalize':
        from PIL import Image, ImageChops
        data=(OUT/'darty-front-1200.jpg').read_bytes()
        im=Image.open(OUT/'darty-front-1200.jpg');im.verify()
        im=Image.open(OUT/'darty-front-1200.jpg').convert('RGB')
        mask=im.point(lambda x:255 if x<240 else 0).convert('L').point(lambda x:255 if x else 0)
        bbox=mask.getbbox()
        text=(OUT/'darty-page.html').read_text()
        ld=[json.loads(x) for x in re.findall(r'<script type="application/ld\+json">(.*?)</script>',text,re.S)]
        proof=dict(model='GFM61MCCSD',ean='8806091257659',source_page='https://martinique.darty-dom.com/p/lg-gfm61mccsd',structured_data=ld,downloaded_html_sha256=hashlib.sha256((OUT/'darty-page.html').read_bytes()).hexdigest(),image_html_association='img-product data-zoom title LG GFM61MCCSD n°1',comparison_pdf=str(ROOT/'storage/app/private/product-photo-alternatives-20261019/cooling/GFM61MCCSD.pdf'),old_native_dimensions=[152,467],new_canvas_dimensions=list(im.size),new_object_bbox_threshold_240=bbox,visual_checks=['Noir carbone, brossé','Porte unique','Poignée verticale extérieure côté droit','Logo LG coin supérieur droit','Afficheur frontal centré, ThinQ et -19','Socle/pieds et proportions identiques à la fiche LG exacte'],date='2026-10-19')
        (OUT/'matching-proof.json').write_text(json.dumps(proof,indent=2,ensure_ascii=False)+'\n')
        result=dict(slug='lg-gfm61mccsd-une-porte',reference='GFM61MCCSD',ean='8806091257659',status='ready',image_path=str(OUT/'darty-front-1200.jpg'),sha256=hashlib.sha256(data).hexdigest(),width=im.width,height=im.height,bytes=len(data),format='JPEG',source_page=proof['source_page'],source_image='https://martinique.darty-dom.com/thumbnails/product/470/470997/box/1200/1200/20/470997.jpg',source_publisher='LG / galerie produit distributeur Darty Martinique',method='Téléchargement du JPEG 1200×1200 explicitement publié dans data-zoom du modèle exact ; aucun agrandissement ni modification locale',matching_evidence='HTML Darty : titre LG GFM61MCCSD, JSON-LD GTIN 8806091257659, couleur Carbone et photo n°1 reliée au même produit. Apparence comparée à la fiche LG archivée exacte modèle+EAN : façade noire carbone, poignée extérieure à droite, écran ThinQ centré, logo LG et pieds concordants.',rights_note='DECISION-016 : autorisation utilisateur des médias fabricant pour HOMYA ; déclaration conservée, contrats non audités. Attribution LG sur la base de la galerie cohérente avec le visuel constructeur, sans licence libre inventée.',visual_review='verified',quality_note='1200×1200 publié par Darty ; silhouette environ 290×900 pixels contre 152×467 auparavant. Aucun rééchantillonnage local. Résolution du master amont non fournie par distributeur.',transform={'resampled':False,'source_dimensions':[1200,1200]},proof_path=str(OUT/'matching-proof.json'))
        (OUT/'results.json').write_text(json.dumps([result],indent=2,ensure_ascii=False)+'\n')
        print(json.dumps({k:result[k] for k in ['reference','sha256','width','height','bytes']},indent=2))
