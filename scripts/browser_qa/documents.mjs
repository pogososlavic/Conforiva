/** Static local QA scenario. Never interpolate credentials into this source. */
import { readInvoiceFixturePath } from './security.mjs';

export default async function (page, { USERNAME, PASSWORD, newContext, request, requestFor, screenshot, ROOT, screenshotsOnly }) {
 page.setDefaultTimeout(15000);
 const base='http://127.0.0.1:8080';
 if (screenshotsOnly) {
  const invoicePath = readInvoiceFixturePath(ROOT);
  // Each run has a fresh context: screenshot mode must authenticate again.
  await page.goto(base+'/admin/connexion');
  await page.locator('[name=username]').fill(USERNAME);
  await page.locator('[name=password]').fill(PASSWORD);
  await page.getByRole('button',{name:'Se connecter au dashboard',exact:false}).click();
  await page.getByRole('heading',{name:'Vue d’ensemble',exact:true}).waitFor();
  await page.setViewportSize({width:375,height:1000});
  await page.goto(base+invoicePath);
  await page.getByRole('heading',{name:'Facture · Brouillon',exact:true}).waitFor();
  if (!(await page.locator('[name="customer[name]"]').inputValue()).startsWith('RECETTE DOCUMENTS invoice')) {
   throw new Error('FIXTURE_ONLY');
  }
  await screenshot(page,{path:'output/playwright/documents-mobile-top.png'});
  await page.locator('.admin-document-summary').scrollIntoViewIfNeeded();
  await screenshot(page,{path:'output/playwright/documents-mobile-issue.png'});
  await page.setViewportSize({width:1440,height:1000});
  await page.goto(base+'/admin/documents');
  return {screenshots:2,synthetic:true};
 }
 const result={stage:'login',pages:[],errors:[],external:[],drafts:[]};
 page.on('pageerror',()=>result.errors.push('javascript-error'));
 page.on('request',r=>{if(!r.url().startsWith(base) && !r.url().startsWith('data:'))result.external.push(new URL(r.url()).hostname)});
 try {
  await page.setViewportSize({width:1440,height:1000});
  await page.goto(base+'/admin/documents');
  if(new URL(page.url()).pathname==='/admin/connexion') {
   await page.locator('[name=username]').fill(USERNAME);
   await page.locator('[name=password]').fill(PASSWORD);
   await page.getByRole('button',{name:'Se connecter au dashboard',exact:false}).click();
   await page.getByRole('heading',{name:'Vue d’ensemble',exact:true}).waitFor();
  }
  result.login=true;
  const check=async (path,name,width)=>{
   await page.setViewportSize({width,height:1000});
   const response=await page.goto(base+path);
   const row={name,width,status:response.status(),h1:await page.locator('h1').count(),overflow:await page.evaluate(()=>document.documentElement.scrollWidth>innerWidth+1),private:response.headers()['cache-control']==='no-store, private',noindex:response.headers()['x-robots-tag']==='noindex, nofollow'};
   result.pages.push(row);
   if(width===1440 || width===375) await screenshot(page, {path:`output/playwright/documents-${name}-${width}.png`,fullPage:true});
  };
  result.stage='create-drafts';
  for (const [type,label] of [['quote','Devis'],['purchase_order','Bon de commande'],['invoice','Facture']]) {
   await page.goto(base+'/admin/documents');
   // A replay of this recipe reuses its named draft rather than creating more copies.
   await page.locator('[name=q]').fill('RECETTE DOCUMENTS '+type);
   await page.getByRole('button',{name:'Rechercher',exact:true}).click();
   const edit=page.getByRole('link',{name:'Modifier',exact:true}).first();
   if(await edit.count()) { await edit.click(); }
   else {
    await page.getByRole('button',{name:'+ '+label,exact:true}).click();
    await page.locator('[data-document-editor]').waitFor();
   }
   const path=new URL(page.url()).pathname;
   const editor=page.locator('[data-document-editor]');
   await editor.locator('[name="customer[name]"]').fill('RECETTE DOCUMENTS '+type+' — NON COMMERCIAL');
   await editor.locator('[name="customer[email]"]').fill('recette@example.invalid');
   await editor.locator('[name="customer[address]"]').fill('12 rue de démonstration');
   await editor.locator('[name="customer[postal_code]"]').fill('75001');
   await editor.locator('[name="customer[city]"]').fill('Paris');
   await editor.locator('[name="customer[country]"]').fill('FR');
   const first=editor.locator('[data-document-line]').first();
   await first.locator('[name$="[reference]"]').fill('RECETTE-001');
   await first.locator('[name$="[name]"]').fill('Produit fictif <script>window.fixtureExecuted=true</script>');
   await first.locator('[name$="[price]"]').fill('10.00');
   if(await editor.locator('[data-document-line]').count()===1)await editor.getByRole('button',{name:'+ Ajouter un article',exact:true}).click();
   const second=editor.locator('[data-document-line]').nth(1);
   await second.locator('[name$="[reference]"]').fill('RECETTE-002');
   await second.locator('[name$="[name]"]').fill('Deuxième article fictif');
   await second.locator('[name$="[quantity]"]').fill('2');
   await second.locator('[name$="[price]"]').fill('12.01');
   await editor.locator('[name=tax_mode]').selectOption(''); // Fiscal data intentionally missing: live issuance remains blocked.
   await editor.locator('[name=payment_terms]').fill('RECETTE — Aucune somme à régler.');
   await editor.locator('[name=delivery_terms]').fill('RECETTE — Aucun envoi réel.');
   await request.get(base+'/admin/notifications',{headers:{'X-Requested-With':'XMLHttpRequest'}});
   await editor.getByRole('button',{name:'Enregistrer le brouillon et recalculer',exact:true}).click();
   await page.getByRole('status').filter({hasText:'Brouillon enregistré'}).waitFor();
   result.drafts.push({type,path,redirectCorrect:new URL(page.url()).pathname===path,total:await page.locator('.admin-document-summary').innerText().then(t=>t.includes('34,02')),blocked:await page.getByRole('button',{name:'Émettre et archiver le PDF',exact:true}).isDisabled()});
   result.noInjectedScript=await page.evaluate(()=>!window.fixtureExecuted);
   const pdf=await request.get(base+path+'/pdf');
   result.drafts.at(-1).pdfStatus=pdf.status();
   result.drafts.at(-1).pdf=pdf.status()===200 && pdf.headers()['content-type']==='application/pdf' && (await pdf.body()).subarray(0,5).toString()==='%PDF-';
   result.drafts.at(-1).privatePdf=pdf.headers()['cache-control']==='no-store, private';
   for(const width of [1440,375,320])await check(path,type,width);
  }
  result.stage='search';
  for(const width of [1440,375,320])await check('/admin/documents?q=RECETTE%20DOCUMENTS','index',width);
  const csrf=await request.post(base+result.drafts[0].path+'/emettre',{form:{revision:'1'}});
  result.csrfRejected=csrf.status()===419;

  const outsider=await newContext();
  const hidden=await requestFor(outsider).get(base+result.drafts[0].path+'/pdf',{maxRedirects:0});
  result.privateStatus=hidden.status();result.privateRedirect=new URL(hidden.headers()['location']||'/',base).pathname;
  result.privateAccess=hidden.status()===302 && hidden.headers()['location'].endsWith('/admin/connexion');
  await outsider.close();
  result.stage='without-js';
  const plain=await newContext({javaScriptEnabled:false});
  const plainPage=await plain.newPage();
  await plainPage.goto(base+'/admin/connexion');
  await plainPage.locator('[name=username]').fill(USERNAME);
  await plainPage.locator('[name=password]').fill(PASSWORD);
  await plainPage.getByRole('button',{name:'Se connecter au dashboard',exact:false}).click();
  await plainPage.getByRole('heading',{name:'Vue d’ensemble',exact:true}).waitFor();
  await plainPage.goto(base+result.drafts[0].path);
  await plainPage.locator('[data-document-editor]').getByRole('button',{name:'Enregistrer le brouillon et recalculer',exact:true}).click();
  result.withoutJs=(await plainPage.locator('body').innerText()).includes('Brouillon enregistré');
  await plain.close();
  await page.setViewportSize({width:1440,height:1000});
  await page.goto(base+'/admin/documents');
  result.stage='done';
 }catch(error){result.failure=true;result.failureKind=error.name==='TimeoutError'?'TimeoutError':'Error';}
 return result;
}
