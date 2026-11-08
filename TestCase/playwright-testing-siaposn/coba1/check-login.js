const { chromium } = require('@playwright/test');

(async () => {
  const browser = await chromium.launch({ headless: true });
  const page = await browser.newPage();

  await page.goto('https://mrcln0305.xyz/login');
  await page.locator('#email').fill('testqa6@gmail.com');
  await page.locator('#password').fill('test_12345678');
  await page.getByRole('button', { name: 'Masuk ke Akun' }).click();
  await page.waitForTimeout(4000);
  console.log('Current URL:', page.url());
  const bodyText = await page.locator('body').innerText();
  console.log('Body snippet:\n', bodyText.slice(0, 300));
  await page.screenshot({ path: 'test-results/probe-login.png' });

  await browser.close();
})();
