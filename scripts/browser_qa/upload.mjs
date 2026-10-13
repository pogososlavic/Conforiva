/** Static local QA scenario. Never interpolate credentials into this source. */
import { readSyntheticUploadFixture } from './security.mjs';

export default async function (page, { USERNAME, PASSWORD, newContext, request, requestFor, screenshot, ROOT, screenshotsOnly }) {
 const result={stage:'login'};
 try {
  const base='http://127.0.0.1:8080';
  await page.goto(base+'/admin/connexion');
  await page.getByLabel('Identifiant',{exact:true}).fill(USERNAME);
  await page.getByLabel('Mot de passe',{exact:true}).fill(PASSWORD);
  await page.getByRole('button',{name:'Se connecter au dashboard'}).click();
  await page.getByRole('heading',{name:'Vue d’ensemble',exact:true}).waitFor();
  result.stage='draft';
  await page.goto(base+'/admin/stocks?q=RECETTE-DASHBOARD-');
  await page.getByRole('link',{name:'Modifier',exact:true}).first().click();
  await page.getByRole('heading',{name:'Modifier le produit',exact:true}).waitFor();
  if(await page.locator('[name=published]').isChecked() || ! (await page.locator('[name=reference]').inputValue()).startsWith('RECETTE-DASHBOARD-')) throw Error('Fixture only');
  result.stage='upload';
  const fixture = readSyntheticUploadFixture();
  await page.locator('[name=image]').setInputFiles(fixture);
  await page.getByRole('button',{name:'Enregistrer le produit',exact:true}).click();
  await page.getByRole('heading',{name:'Modifier le produit',exact:true}).waitFor();
  const image=page.locator('.admin-product-preview').first();
  await image.waitFor();
  result.image=await image.evaluate(el=>({loaded:el.complete && el.naturalWidth===600,webp:new URL(el.src).pathname.endsWith('.webp')}));
  result.unpublished=!(await page.locator('[name=published]').isChecked());
  result.stage='gallery';
  await page.locator('[name="images[]"]').setInputFiles(fixture);
  await page.getByRole('button',{name:'Enregistrer le produit',exact:true}).click();
  await page.getByRole('heading',{name:'Modifier le produit',exact:true}).waitFor();
  result.gallery=await page.locator('.admin-product-preview').count()===2;
  const csrf=await request.post(base+'/admin/parametres',{data:{}});
  result.settingsCsrfRefused=[403,419].includes(csrf.status());
  await page.goto(base+'/admin');
  await page.getByRole('button',{name:'Se déconnecter',exact:true}).click();
  await page.getByRole('heading',{name:'Connexion administrateur',exact:true}).waitFor();
  result.stage='done';
 } catch { result.failure=true; }
 return result;
}
