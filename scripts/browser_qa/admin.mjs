/** Static local QA scenario. Never interpolate credentials into this source. */
export default async function (page, { USERNAME, PASSWORD, newContext, request, requestFor, screenshot, ROOT, screenshotsOnly }) {
 const result = {pages:[], errors:[]};
 page.on('pageerror', () => result.errors.push('pageerror'));
 await page.goto('http://127.0.0.1:8080/admin/connexion');
 await page.getByLabel('Identifiant', {exact:true}).fill(USERNAME);
 await page.getByLabel('Mot de passe', {exact:true}).fill(PASSWORD);
 await page.getByRole('button', {name:'Se connecter au dashboard'}).click();
 await page.getByRole('heading', {name:'Vue d’ensemble', exact:true}).waitFor();
 result.login = new URL(page.url()).pathname === '/admin';
 for (const width of [1440,375,320]) {
  await page.setViewportSize({width,height:1000});
  for (const path of ['/admin','/admin/commandes','/admin/stocks','/admin/retours']) {
   const response = await page.goto('http://127.0.0.1:8080'+path);
   await page.locator('h1').waitFor();
   result.pages.push({path,width,status:response.status(), h1:await page.locator('h1').count(), overflow:await page.evaluate(()=>document.documentElement.scrollWidth>innerWidth), private:response.headers()['cache-control']==='no-store, private', noindex:response.headers()['x-robots-tag']==='noindex, nofollow'});
   if (width === 1440 || width === 375) await screenshot(page, {path:'output/playwright/admin-'+path.split('/').pop()+'-'+width+'.png',fullPage:true});
  }
 }
 await page.goto('http://127.0.0.1:8080/admin/stocks?q=absent-admin-fixture-only');
 result.emptySearch = await page.getByText('Aucun produit pour cette recherche.').count() === 1;
 await page.goto('http://127.0.0.1:8080/admin/stocks');
 const next = page.getByRole('link',{name:'Suivant →'});
 if(await next.count()) { await next.click(); result.pagination = new URL(page.url()).searchParams.get('page') === '2'; }
 await page.goto('http://127.0.0.1:8080/admin/commandes');
 const detail = page.getByRole('link', {name:/Consulter la commande/}).first();
 if(await detail.count()) { await detail.click(); result.orderDetail = await page.getByRole('heading',{name:'Détail de la commande', exact:true}).count() === 1; }
 const badPost = await request.post('http://127.0.0.1:8080/admin/deconnexion',{data:{}});
 result.csrfRefused = [403,419].includes(badPost.status());
 await page.goto('http://127.0.0.1:8080/admin');
 await page.getByRole('button',{name:'Se déconnecter',exact:true}).click();
 await page.getByRole('heading',{name:'Connexion administrateur',exact:true}).waitFor();
 await page.goto('http://127.0.0.1:8080/admin/stocks');
 result.logout = new URL(page.url()).pathname === '/admin/connexion';
 const ctx=await newContext({viewport:{width:375,height:900},javaScriptEnabled:false});
 const p=await ctx.newPage();
 await p.goto('http://127.0.0.1:8080/admin/connexion');
 await p.getByLabel('Identifiant',{exact:true}).fill(USERNAME);
 await p.getByLabel('Mot de passe',{exact:true}).fill(PASSWORD);
 await p.getByRole('button',{name:'Se connecter au dashboard'}).click();
 await p.getByRole('heading',{name:'Vue d’ensemble',exact:true}).waitFor();
 result.noJavascript = new URL(p.url()).pathname === '/admin';
 await ctx.close();
 return result;
}
