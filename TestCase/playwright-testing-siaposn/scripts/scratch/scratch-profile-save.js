const { chromium } = require('@playwright/test');

async function checkProfileSave() {
  const browser = await chromium.launch({ headless: true });
  const context = await browser.newContext();
  const page = await context.newPage();

  let reqPayload = null;
  let resStatus = null;
  let resBody = null;

  page.on('request', req => {
    if (req.url().includes('/api/')) {
      console.log(`[REQ] ${req.method()} ${req.url()}`);
      if (req.postData()) {
        console.log(`Payload: ${req.postData()}`);
      }
    }
  });

  page.on('response', async resp => {
    if (resp.url().includes('/api/')) {
      let text = '';
      try { text = await resp.text(); } catch (e) {}
      console.log(`[RESP ${resp.status()}] ${resp.url()} -> ${text.slice(0, 200)}`);
    }
  });

  await page.goto('https://mrcln0305.xyz/login', { waitUntil: 'networkidle' });
  await page.locator('#email').fill('testqa6@gmail.com');
  await page.locator('#password').fill('test_12345678');
  await page.getByRole('button', { name: 'Masuk ke Akun' }).click();
  await page.waitForURL(/\/siswa/, { timeout: 15000 });

  await page.goto('https://mrcln0305.xyz/siswa/profil', { waitUntil: 'networkidle' });
  await page.waitForTimeout(1000);

  await page.getByRole('button', { name: /Ubah Profil/i }).click();
  await page.waitForTimeout(500);

  // Clear name and enter 1 character or invalid
  await page.locator('#nama').fill('');
  await page.getByRole('button', { name: /Simpan/i }).click();
  await page.waitForTimeout(2000);

  // Check if any error text appears near #nama
  const errors = await page.locator('#nama ~ span, #nama ~ p, .text-red-500').allInnerTexts();
  console.log('Error elements under nama:', errors);

  await page.screenshot({ path: 'test-results/profil-empty-nama-inspect.png' });

  await browser.close();
}

checkProfileSave().catch(console.error);
