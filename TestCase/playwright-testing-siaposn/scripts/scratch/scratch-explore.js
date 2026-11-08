const { chromium } = require('@playwright/test');

async function main() {
  const browser = await chromium.launch({ headless: true });
  const context = await browser.newContext();
  const page = await context.newPage();

  page.on('console', msg => console.log('BROWSER CONSOLE:', msg.type(), msg.text()));
  page.on('pageerror', err => console.log('PAGE ERROR:', err.message));
  page.on('response', async resp => {
    if (resp.status() >= 400) {
      let body = '';
      try { body = await resp.text(); } catch (e) {}
      console.log(`HTTP ${resp.status()} [${resp.request().method()}] ${resp.url()} -> ${body.slice(0, 200)}`);
    }
  });

  console.log('Navigating to login...');
  await page.goto('https://mrcln0305.xyz/login', { waitUntil: 'networkidle' });
  await page.locator('#email').fill('testqa6@gmail.com');
  await page.locator('#password').fill('test_12345678');
  await page.getByRole('button', { name: 'Masuk ke Akun' }).click();

  await page.waitForTimeout(5000);
  console.log('Current URL after login attempt:', page.url());

  // Check visible messages or alerts
  const visibleText = await page.innerText('body');
  console.log('Body snippet:\n', visibleText.slice(0, 500));

  await page.screenshot({ path: 'test-results/explore-login-result.png', fullPage: true });

  await browser.close();
}

main().catch(console.error);
