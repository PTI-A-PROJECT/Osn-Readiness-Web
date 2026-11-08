const { chromium } = require('@playwright/test');

async function testPwSubmit() {
  const browser = await chromium.launch({ headless: true });
  const page = await browser.newPage();
  await page.goto('https://mrcln0305.xyz/login');
  await page.locator('#email').fill('testqa6@gmail.com');
  await page.locator('#password').fill('test_12345678');
  await page.getByRole('button', { name: 'Masuk ke Akun' }).click();
  await page.waitForURL(/\/siswa/);
  await page.goto('https://mrcln0305.xyz/siswa/profil');

  await page.getByRole('button', { name: 'Ubah Kata Sandi' }).click();
  await page.locator('#pw-now').fill('test_12345678');
  await page.locator('#pw-new').fill('test_12345678_new');
  await page.locator('#pw-confirm').fill('test_12345678_new');

  page.on('request', r => console.log('REQ:', r.method(), r.url()));
  page.on('response', r => console.log('RESP:', r.status(), r.url()));

  await page.getByRole('button', { name: 'Simpan' }).click();
  await page.waitForTimeout(2000);

  const textSec2 = await page.locator('section:has-text("02 · KEAMANAN")').first().innerText();
  console.log('Section 02 after clicking Simpan with filled fields:\n', textSec2);
  await browser.close();
}

testPwSubmit().catch(console.error);
