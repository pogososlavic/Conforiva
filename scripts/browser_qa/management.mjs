/** Static local QA scenario. Never interpolate credentials into this source. */
export default async function (page, { USERNAME, PASSWORD, newContext, request, requestFor, screenshot, ROOT, screenshotsOnly }) {
 const result={pages:[],errors:[],externalRequests:[],stage:'start'};
 const base='http://127.0.0.1:8080';
 page.on('pageerror',()=>result.errors.push('pageerror'));
 page.on('request',r=>{if(!r.url().startsWith(base) && !r.url().startsWith('data:')) result.externalRequests.push(new URL(r.url()).hostname)});

 const clientContext=await newContext();
 const client=await clientContext.newPage();
 const outsiderContext=await newContext();
 const outsider=await outsiderContext.newPage();
 try {
  result.stage='customer-message';
  await client.goto(base+'/fr/assistance');
  const first=client.locator('form').filter({has:client.locator('textarea[name=body]')});
  await first.locator('[name=name]').fill('RECETTE DASHBOARD — client fictif');
  await first.locator('[name=body]').fill('Recette locale : question fictive <script>window.fixtureExecuted=true</script>. Aucun envoi externe.');
  await first.getByRole('button',{name:'Envoyer mon message',exact:true}).click();
  await client.getByRole('heading',{name:'Ma conversation avec HOMYA',exact:true}).waitFor();
  const thread=new URL(client.url()).pathname.split('/').pop();
  const accessCode=await client.locator('strong.admin-reference').innerText();
  result.customerCreated=/^[0-9a-f-]{36}$/.test(thread);
  result.literalMessage=await client.locator('.admin-message').innerText().then(t=>t.includes('<script>'));
  result.noInjectedScript=await client.evaluate(()=>window.fixtureExecuted!==true);
  const blocked=await outsider.goto(base+'/fr/assistance/'+thread);
  result.privateConversation=blocked.status()===404;
  result.stage='admin-login';
  await page.goto(base+'/admin/connexion');
  await page.getByLabel('Identifiant',{exact:true}).fill(USERNAME);
  await page.getByLabel('Mot de passe',{exact:true}).fill(PASSWORD);
  await page.getByRole('button',{name:'Se connecter au dashboard'}).click();
  await page.getByRole('heading',{name:'Vue d’ensemble',exact:true}).waitFor();
  result.login=new URL(page.url()).pathname==='/admin';
  result.stage='admin-reply';
  await page.goto(base+'/admin/messages/'+thread);
  await page.getByLabel('Votre réponse',{exact:true}).fill('RECETTE — réponse administrateur consultable dans le dossier privé.');
  await page.getByRole('button',{name:'Publier ma réponse',exact:true}).click();
  await page.getByText('RECETTE — réponse administrateur consultable dans le dossier privé.',{exact:true}).waitFor();
  result.replyStored=await page.locator('.admin-message').count()===2;
  result.noEmail=await page.getByText('Réponse disponible dans le dossier client.',{exact:true}).count()===1;
  await client.reload();
  result.customerReceived=await client.getByText('RECETTE — réponse administrateur consultable dans le dossier privé.',{exact:true}).count()===1;
  result.stage='resume';
  await outsider.goto(base+'/fr/assistance');
  await outsider.locator('[name=id]').fill(thread);
  await outsider.locator('[name=code]').fill(accessCode);
  await outsider.getByRole('button',{name:'Ouvrir mon dossier',exact:true}).click();
  await outsider.getByRole('heading',{name:'Ma conversation avec HOMYA',exact:true}).waitFor();
  result.resumed=await outsider.locator('.admin-message').count()===2;
  await outsider.getByLabel('Message',{exact:true}).fill('RECETTE — retour client fictif après lecture.');
  await outsider.getByRole('button',{name:'Envoyer mon message',exact:true}).click();
  await outsider.getByText('RECETTE — retour client fictif après lecture.',{exact:true}).waitFor();
  result.customerFollowup=await outsider.locator('.admin-message').count()===3;
  result.stage='draft-product';
  const reference='RECETTE-DASHBOARD-'+Date.now();
  await page.goto(base+'/admin/produits/nouveau');
  const form=page.locator('form').filter({has:page.locator('[name=reference]')});
  await form.locator('[name=reference]').fill(reference);
  await form.locator('[name=price]').fill('1.00');
  await form.locator('[name=quantity]').fill('0');
  await form.locator('[name=brand]').fill('RECETTE — brouillon fictif');
  await form.locator('[name=category]').fill('Recette interne');
  await form.locator('[name="languages[fr][name]"]').fill('RECETTE DASHBOARD — ne pas publier');
  await form.locator('[name="languages[fr][category]"]').fill('Recette interne');
  await form.locator('[name="languages[fr][description]"]').fill('Brouillon de recette locale, sans stock ni vente.');
  await form.getByRole('button',{name:'Enregistrer le produit',exact:true}).click();
  await page.getByRole('heading',{name:'Modifier le produit',exact:true}).waitFor();
  const editPath=new URL(page.url()).pathname;
  const slug=editPath.split('/').pop();
  result.draftCreated=await page.locator('[name=reference]').inputValue()===reference;
  await page.locator('[name=price]').fill('2.00');
  await page.locator('[name="languages[fr][description]"]').fill('Brouillon de recette locale modifié depuis le dashboard.');
  await page.getByRole('button',{name:'Enregistrer le produit',exact:true}).click();
  await page.getByRole('heading',{name:'Modifier le produit',exact:true}).waitFor();
  result.draftEdited=await page.locator('[name=price]').inputValue()==='2.00' && await page.locator('[name=revision]').inputValue()==='2';
  result.draftUnpublished=!(await page.locator('[name=published]').isChecked());
  const notPublic=await client.goto(base+'/fr/produit/'+slug);
  result.draftHidden=notPublic.status()===404;
  result.stage='visual-pages';
  const paths=['/admin','/admin/commandes','/admin/stocks','/admin/retours','/admin/livraisons','/admin/messages','/admin/messages/'+thread,'/admin/activite','/admin/parametres','/admin/integrations',editPath];
  for(const width of [1440,375,320]) {
   await page.setViewportSize({width,height:1000});
   for(let index=0;index<paths.length;index++) {
    const response=await page.goto(base+paths[index]);
    await page.locator('h1').waitFor();
    result.pages.push({index,width,status:response.status(),h1:await page.locator('h1').count(),overflow:await page.evaluate(()=>document.documentElement.scrollWidth>innerWidth),private:response.headers()['cache-control']==='no-store, private',noindex:response.headers()['x-robots-tag']==='noindex, nofollow'});
    if(width!==320) await screenshot(page, {path:'output/playwright/admin-management-live-'+index+'-'+width+'.png',fullPage:true});
   }
  }
  result.stage='guards';
  const counts=await request.get(base+'/admin/notifications',{headers:{'X-Requested-With':'XMLHttpRequest'}});
  const notification=await counts.json();
  result.notifications=counts.status()===200 && notification.messages>=1;
  const csrf=await request.post(base+'/admin/messages/'+thread,{data:{body:'Must not save'}});
  result.csrfRefused=[403,419].includes(csrf.status());
  await page.goto(base+'/admin/messages/'+thread);
  result.chronological=await page.locator('.admin-message').count()===3;
  await page.getByRole('button',{name:'Marquer comme lu',exact:true}).click();
  await page.getByRole('heading',{name:'Conversation client',exact:true}).waitFor();
  const done=await request.get(base+'/admin/notifications',{headers:{'X-Requested-With':'XMLHttpRequest'}}).then(r=>r.json());
  result.readState=done.messages===notification.messages-1;
  await page.getByRole('button',{name:'Se déconnecter',exact:true}).click();
  await page.getByRole('heading',{name:'Connexion administrateur',exact:true}).waitFor();
  await page.goto(base+'/admin/messages');
  result.logout=new URL(page.url()).pathname==='/admin/connexion';
  result.stage='done';
 } catch(error) { result.failure=true; }
 finally { await clientContext.close(); await outsiderContext.close(); }
 return result;
}
