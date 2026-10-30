const { test, expect } = require('@playwright/test');
test.beforeEach(async ({page}) => {
 await page.goto('/demo.html');
 await page.waitForFunction(() => !!document.querySelector('#geo').geoPicker);
});
test('optional, search, cancel preserves article',async ({page})=>{
 await expect(page.locator('[data-geo="panel"]')).toBeHidden();
 await page.locator('[data-geo="enable"]').check();
 await page.locator('[data-geo="search"]').fill('Sobral');
 await page.locator('[data-geo="search-button"]').click();
 await page.getByRole('button',{name:'Sobral, CE'}).click();
 await expect(page.locator('[data-geo="latitude"]')).toHaveValue('-3.68274');
 await page.locator('[data-geo="cancel"]').click();
 await expect(page.locator('#output')).toHaveText('null');
 await expect(page.locator('#article')).toHaveValue('Um texto editorial já escrito.');
});
test('keyboard and explicit confirmation',async ({page})=>{
 await page.locator('[data-geo="enable"]').check();
 await page.locator('[data-geo="search"]').fill('Recife');
 await page.locator('[data-geo="search"]').press('Enter');
 await page.getByRole('button',{name:'Recife, PE'}).focus();
 await page.keyboard.press('Enter');
 await page.locator('[data-geo="precisao"]').selectOption('municipio');
 await page.locator('[data-geo="tipo_local"]').selectOption('evento');
 await page.locator('[data-geo="exibir_no_mapa"]').check();
 await page.locator('[data-geo="confirm"]').click();
 await expect(page.locator('[data-geo="status"]')).toContainText('Confirme explicitamente');
 await page.locator('[data-geo="consent"]').check();
 await page.locator('[data-geo="confirm"]').click();
 expect(JSON.parse(await page.locator('#output').innerText())).toEqual({latitude:-8.0476,longitude:-34.877,precisao:'municipio',tipo_local:'evento',exibir_no_mapa:true});
});
test('invalid coordinates do not confirm',async ({page})=>{
 await page.locator('[data-geo="enable"]').check();
 await page.locator('[data-geo="latitude"]').fill('1000');
 await page.locator('[data-geo="longitude"]').fill('500');
 await page.locator('[data-geo="consent"]').check();
 await page.locator('[data-geo="confirm"]').click();
 await expect(page.locator('#output')).toHaveText('null');
});
test('GPS denied',async ({page})=>{
 await page.addInitScript(()=>Object.defineProperty(navigator,'geolocation',{configurable:true,value:{getCurrentPosition:(_,failure)=>failure({code:1})}}));
 await page.reload(); await page.waitForFunction(()=>!!document.querySelector('#geo').geoPicker);
 await page.locator('[data-geo="enable"]').check();
 await page.locator('[data-geo="gps"]').click();
 await expect(page.locator('[data-geo="status"]')).toContainText('negada');
 await expect(page.locator('#output')).toHaveText('null');
});
test('GPS mock success needs consent',async ({page})=>{
 await page.addInitScript(()=>Object.defineProperty(navigator,'geolocation',{configurable:true,value:{getCurrentPosition:(success)=>success({coords:{latitude:-22.9,longitude:-43.1,accuracy:1400}})}}));
 await page.reload(); await page.waitForFunction(()=>!!document.querySelector('#geo').geoPicker);
 await page.locator('[data-geo="enable"]').check();
 await page.locator('[data-geo="gps"]').click();
 await expect(page.locator('[data-geo="latitude"]')).toHaveValue('-22.9');
 await expect(page.locator('[data-geo="status"]')).toContainText('impreciso');
 await expect(page.locator('#output')).toHaveText('null');
 await page.locator('[data-geo="consent"]').check();
 await page.locator('[data-geo="confirm"]').click();
 await expect(page.locator('#output')).toContainText('-22.9');
});
test('cancel invalidates late GPS response',async ({page})=>{
 await page.addInitScript(()=>Object.defineProperty(navigator,'geolocation',{configurable:true,value:{getCurrentPosition:(success)=>window.pendingGPS=success}}));
 await page.reload(); await page.waitForFunction(()=>!!document.querySelector('#geo').geoPicker);
 await page.locator('[data-geo="enable"]').check();
 await page.locator('[data-geo="gps"]').click();
 await page.locator('[data-geo="cancel"]').click();
 await page.evaluate(()=>window.pendingGPS({coords:{latitude:1,longitude:1,accuracy:5}}));
 await expect(page.locator('#output')).toHaveText('null');
});