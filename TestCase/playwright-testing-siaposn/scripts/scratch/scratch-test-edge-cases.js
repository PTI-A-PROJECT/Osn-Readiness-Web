const { chromium } = require('@playwright/test');

async function testEdgeCases() {
  const browser = await chromium.launch({ headless: true });
  const context = await browser.newContext({ viewport: { width: 1280, height: 800 } });
  const page = await context.newPage();

  const results = {};

  page.on('console', msg => {
    if (msg.type() === 'error') {
      console.log('[BROWSER ERR]', msg.text());
    }
  });

  page.on('response', async resp => {
    if (resp.status() >= 400) {
      let body = '';
      try { body = await resp.text(); } catch (e) {}
      console.log(`[HTTP ${resp.status()}] ${resp.request().method()} ${resp.url()} -> ${body.slice(0, 150)}`);
    }
  });

  await page.goto('https://mrcln0305.xyz/login', { waitUntil: 'networkidle' });
  await page.locator('#email').fill('testqa6@gmail.com');
  await page.locator('#password').fill('test_12345678');
  await page.getByRole('button', { name: 'Masuk ke Akun' }).click();
  await page.waitForURL(/\/siswa/, { timeout: 15000 });

  // TEST 1: Materi Tingkat Provinsi (403 forbidden handling)
  console.log('--- TEST 1: Materi Tingkat Provinsi ---');
  await page.goto('https://mrcln0305.xyz/siswa/materi', { waitUntil: 'networkidle' });
  await page.waitForTimeout(1000);
  // select tingkat dropdown option "2" (Provinsi)
  const tingkatSelect = page.locator('select').first();
  await tingkatSelect.selectOption('2');
  await page.waitForTimeout(1500);
  results.materiProvinsiText = await page.innerText('main');
  console.log('Materi with Provinsi selected:\n', results.materiProvinsiText.slice(0, 300));
  await page.screenshot({ path: 'test-results/materi-tingkat-provinsi-403.png', fullPage: true });

  // TEST 2: Materi non-existent ID
  console.log('--- TEST 2: Materi Non-existent ID /siswa/materi/9999 ---');
  await page.goto('https://mrcln0305.xyz/siswa/materi/9999', { waitUntil: 'networkidle' });
  await page.waitForTimeout(1000);
  results.materi9999Text = await page.innerText('main, body');
  console.log('Materi 9999 response:\n', results.materi9999Text.slice(0, 250));
  await page.screenshot({ path: 'test-results/materi-9999.png', fullPage: true });

  // TEST 3: Latihan non-numeric ID /siswa/latihan/abc
  console.log('--- TEST 3: Latihan non-numeric ID /siswa/latihan/abc ---');
  await page.goto('https://mrcln0305.xyz/siswa/latihan/abc', { waitUntil: 'networkidle' });
  await page.waitForTimeout(1000);
  results.latihanAbcText = await page.innerText('main, body');
  console.log('Latihan abc response:\n', results.latihanAbcText.slice(0, 250));
  await page.screenshot({ path: 'test-results/latihan-abc.png', fullPage: true });

  // TEST 4: Simulasi Ujian non-existent ID /siswa/simulasi/ujian/9999
  console.log('--- TEST 4: Simulasi Ujian /siswa/simulasi/ujian/9999 ---');
  await page.goto('https://mrcln0305.xyz/siswa/simulasi/ujian/9999', { waitUntil: 'networkidle' });
  await page.waitForTimeout(1000);
  results.simulasi9999Text = await page.innerText('main, body');
  console.log('Simulasi ujian 9999 response:\n', results.simulasi9999Text.slice(0, 250));
  await page.screenshot({ path: 'test-results/simulasi-ujian-9999.png', fullPage: true });

  // TEST 5: Profil: Referral Code input editable state
  console.log('--- TEST 5: Referral Code Editable State & Profile Save ---');
  await page.goto('https://mrcln0305.xyz/siswa/profil', { waitUntil: 'networkidle' });
  await page.waitForTimeout(1000);

  // Look at the referral input
  const refInput = page.locator('input[value="P4QU"]');
  const isRefDisabled = await refInput.isDisabled();
  const refInputId = await refInput.getAttribute('id');
  const refInputName = await refInput.getAttribute('name');
  const refInputReadonly = await refInput.getAttribute('readonly');
  console.log(`Referral Input: disabled=${isRefDisabled}, readonly=${refInputReadonly}, id=${refInputId}, name=${refInputName}`);

  // Test Profile Edit Validation
  await page.getByRole('button', { name: /Ubah Profil/i }).click();
  await page.waitForTimeout(500);

  // Empty nama
  await page.locator('#nama').fill('');
  // Find Simpan button
  const simpanBtn = page.getByRole('button', { name: /Simpan/i });
  console.log('Simpan button visible:', await simpanBtn.isVisible());
  if (await simpanBtn.isVisible()) {
    await simpanBtn.click();
    await page.waitForTimeout(1000);
    const profilAlert = await page.locator('.alert, [role="alert"], .text-red-500, .toast').allInnerTexts();
    console.log('Profile empty nama validation alerts:', profilAlert);
    await page.screenshot({ path: 'test-results/profil-empty-nama.png' });
  }

  // TEST 6: Mobile Responsiveness / Layout Break on Small Viewport (375x667)
  console.log('--- TEST 6: Mobile Viewport 375x667 ---');
  await page.setViewportSize({ width: 375, height: 667 });
  await page.goto('https://mrcln0305.xyz/siswa', { waitUntil: 'networkidle' });
  await page.waitForTimeout(1000);
  await page.screenshot({ path: 'test-results/mobile-dashboard.png', fullPage: true });

  await page.goto('https://mrcln0305.xyz/siswa/simulasi/ujian/1', { waitUntil: 'networkidle' });
  await page.waitForTimeout(1500);
  await page.screenshot({ path: 'test-results/mobile-simulasi-ujian.png', fullPage: true });

  await browser.close();
}

testEdgeCases().catch(console.error);
