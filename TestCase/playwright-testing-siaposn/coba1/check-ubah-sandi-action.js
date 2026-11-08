const { chromium } = require('@playwright/test');

(async () => {
  const browser = await chromium.launch({ headless: true });
  const page = await browser.newPage();

  await page.goto('https://mrcln0305.xyz/login');
  await page.locator('#email').fill('testqa6@gmail.com');
  await page.locator('#password').fill('test_12345678');
  await page.getByRole('button', { name: 'Masuk ke Akun' }).click();
  await page.waitForTimeout(3000);

  await page.goto('https://mrcln0305.xyz/siswa/profil');
  await page.waitForTimeout(2000);

  const btnUbahPw = page.getByRole('button', { name: /Ubah Kata Sandi/i });
  console.log('Button Ubah Kata Sandi HTML:', await btnUbahPw.evaluate(el => el.outerHTML));
  await btnUbahPw.click();
  await page.waitForTimeout(1500);

  console.log('Current URL after click:', page.url());
  const allDialogs = await page.locator('*[role="dialog"], .modal, .popup, form').evaluateAll(els => els.map(e => ({ tag: e.tagName, class: e.className, text: e.innerText.slice(0, 100) })));
  console.log('Any dialog or form appeared?', allDialogs);

  await page.screenshot({ path: 'test-results/profil-after-click-ubah-sandi.png', fullPage: true });

  await browser.close();
})();
