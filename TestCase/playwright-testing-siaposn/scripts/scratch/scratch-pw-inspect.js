const { chromium } = require('@playwright/test');

async function inspectPasswordInputs() {
  const browser = await chromium.launch({ headless: true });
  const page = await browser.newPage();
  await page.goto('https://mrcln0305.xyz/login');
  await page.locator('#email').fill('testqa6@gmail.com');
  await page.locator('#password').fill('test_12345678');
  await page.getByRole('button', { name: 'Masuk ke Akun' }).click();
  await page.waitForURL(/\/siswa/);
  await page.goto('https://mrcln0305.xyz/siswa/profil');

  const before = await page.$$eval('input[type="password"]', els => els.map(e => ({
    id: e.id,
    name: e.name,
    placeholder: e.placeholder,
    visible: e.offsetParent !== null,
    html: e.outerHTML
  })));
  console.log('Password inputs before click:', before);

  await page.getByRole('button', { name: 'Ubah Kata Sandi' }).click();
  await page.waitForTimeout(500);

  const after = await page.$$eval('input[type="password"]', els => els.map(e => ({
    id: e.id,
    name: e.name,
    placeholder: e.placeholder,
    visible: e.offsetParent !== null,
    html: e.outerHTML
  })));
  console.log('Password inputs after click:', after);

  // Check section 02 card text
  const sec2 = await page.locator('section:has-text("02 · KEAMANAN")').first().innerText();
  console.log('Section 02 text:\n', sec2);

  await browser.close();
}

inspectPasswordInputs().catch(console.error);
