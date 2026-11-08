const { chromium } = require('@playwright/test');

async function inspectModalBtn() {
  const browser = await chromium.launch({ headless: true });
  const page = await browser.newPage();
  page.on('console', m => console.log('LOG:', m.text()));

  await page.goto('https://mrcln0305.xyz/login');
  await page.locator('#email').fill('testqa6@gmail.com');
  await page.locator('#password').fill('test_12345678');
  await page.getByRole('button', { name: 'Masuk ke Akun' }).click();
  await page.waitForURL(/\/siswa/);
  await page.goto('https://mrcln0305.xyz/siswa/simulasi/ujian/1');
  await page.waitForTimeout(1500);

  // Click 30 -> Selesai
  await page.locator('button.nomer:has-text("30")').click();
  await page.waitForTimeout(500);
  await page.getByRole('button', { name: /Selesai/i }).click();
  await page.waitForTimeout(1000);

  const btnHtml = await page.locator('.modal button, [role="dialog"] button').evaluateAll(els => els.map(b => ({
    text: b.innerText,
    html: b.outerHTML,
    disabled: b.disabled
  })));
  console.log('Modal buttons details:\n', JSON.stringify(btnHtml, null, 2));

  await browser.close();
}

inspectModalBtn().catch(console.error);
