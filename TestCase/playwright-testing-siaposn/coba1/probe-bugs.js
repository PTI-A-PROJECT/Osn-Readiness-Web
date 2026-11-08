const { chromium } = require('@playwright/test');

(async () => {
  const browser = await chromium.launch({ headless: true });
  const page = await browser.newPage();

  // 1. Login
  await page.goto('https://mrcln0305.xyz/login');
  await page.locator('#email').fill('testqa6@gmail.com');
  await page.locator('#password').fill('test_12345678');
  await page.getByRole('button', { name: 'Masuk ke Akun' }).click();
  await page.waitForTimeout(4000);
  console.log('Login URL:', page.url());

  // 2. Test Unduh Laporan on /siswa/pemetaan
  console.log('\n--- Test 1: Unduh Laporan on /siswa/pemetaan ---');
  await page.getByRole('link', { name: 'Pemetaan Kompetensi' }).click();
  await page.waitForTimeout(3000);
  const unduhBtn = page.getByRole('button', { name: /Unduh laporan/i });
  console.log('Unduh button visible?', await unduhBtn.isVisible());
  if (await unduhBtn.isVisible()) {
    let dlTriggered = false;
    page.on('download', dl => {
      dlTriggered = true;
      console.log('Download event fired! Filename:', dl.suggestedFilename());
    });
    let apiStatus = null;
    page.on('response', res => {
      if (res.url().includes('laporan') || res.url().includes('pdf') || res.url().includes('download')) {
        apiStatus = { status: res.status(), url: res.url() };
        console.log('Report API response:', apiStatus);
      }
    });

    await unduhBtn.click();
    await page.waitForTimeout(4000);
    console.log('Did download trigger?', dlTriggered);
    await page.screenshot({ path: 'test-results/probe-unduh-laporan.png', fullPage: true });
  }

  // 3. Test Invalid Quiz ID (Laravel exception leakage)
  console.log('\n--- Test 2: Invalid Quiz ID /siswa/latihan/99 ---');
  await page.goto('https://mrcln0305.xyz/siswa/latihan/99');
  await page.waitForTimeout(3000);
  const errorText = await page.locator('main').first().innerText();
  console.log('Error page snippet:', errorText.slice(0, 300));
  await page.screenshot({ path: 'test-results/probe-raw-exception-leak.png', fullPage: true });

  // 4. Test Profile Page
  console.log('\n--- Test 3: Profile Page /siswa/profil ---');
  await page.goto('https://mrcln0305.xyz/siswa/profil');
  await page.waitForTimeout(3000);
  console.log('Profil page text:\n', (await page.locator('main').first().innerText()).slice(0, 500));
  await page.screenshot({ path: 'test-results/probe-profil.png', fullPage: true });

  // 5. Test Direct URL on /siswa/simulasi
  console.log('\n--- Test 4: Direct Navigation to /siswa/simulasi ---');
  await page.goto('https://mrcln0305.xyz/siswa/simulasi');
  await page.waitForTimeout(3000);
  const simText = await page.locator('main').first().innerText();
  console.log('Direct simulasi text snippet:\n', simText.slice(0, 400));
  console.log('Contains "Belum ada data tingkat"?', simText.includes('Belum ada data tingkat'));
  await page.screenshot({ path: 'test-results/probe-simulasi-direct.png', fullPage: true });

  // 6. Test Register Form Validation Text
  console.log('\n--- Test 5: Register Form Validation Feedback ---');
  const context2 = await browser.newContext();
  const page2 = await context2.newPage();
  await page2.goto('https://mrcln0305.xyz/register');
  await page2.locator('#nama').fill('Test Invalid');
  await page2.locator('#email').fill('test.invalid@example.com');
  await page2.locator('#password').fill('123');
  await page2.locator('#konfirmasi').fill('123');
  await page2.locator('#agreement').check();
  await page2.getByRole('button', { name: /Daftar Sekarang/i }).click();
  await page2.waitForTimeout(1500);

  const errorMessages = await page2.locator('#password ~ span, #password ~ p, .error-message, .text-red-500, [role="alert"]').allInnerTexts();
  console.log('Visible error message elements around password:', errorMessages);
  await page2.screenshot({ path: 'test-results/probe-register-validation.png', fullPage: true });

  await browser.close();
  console.log('\n=== Probe Completed Successfully ===');
})();
