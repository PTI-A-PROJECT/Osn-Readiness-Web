const { chromium } = require('@playwright/test');

async function testProfileValidation() {
  const browser = await chromium.launch({ headless: true });
  const page = await browser.newPage();

  page.on('response', async resp => {
    if (resp.url().includes('/api/')) {
      let body = '';
      try { body = await resp.text(); } catch (e) {}
      console.log(`[API ${resp.status()}] ${resp.request().method()} ${resp.url()} -> ${body.slice(0, 150)}`);
    }
  });

  await page.goto('https://mrcln0305.xyz/login', { waitUntil: 'networkidle' });
  await page.locator('#email').fill('testqa6@gmail.com');
  await page.locator('#password').fill('test_12345678');
  await page.getByRole('button', { name: 'Masuk ke Akun' }).click();
  await page.waitForURL(/\/siswa/, { timeout: 15000 });

  await page.goto('https://mrcln0305.xyz/siswa/profil', { waitUntil: 'networkidle' });
  await page.getByRole('button', { name: /Ubah Profil/i }).click();
  await page.waitForTimeout(500);

  // 1. Test empty name
  console.log('Testing empty name...');
  await page.locator('#nama').fill('');
  await page.getByRole('button', { name: 'Simpan Perubahan' }).click();
  await page.waitForTimeout(1500);

  const errorsNama = await page.locator('.field .error, .text-red-500, [role="alert"]').allInnerTexts();
  console.log('Errors on empty nama:', errorsNama);

  // 2. Test invalid email format
  console.log('Testing invalid email format...');
  await page.locator('#nama').fill('testqa6');
  await page.locator('#email').fill('bukan-email');
  await page.getByRole('button', { name: 'Simpan Perubahan' }).click();
  await page.waitForTimeout(1500);

  const errorsEmail = await page.locator('.field .error, .text-red-500, [role="alert"]').allInnerTexts();
  console.log('Errors on invalid email:', errorsEmail);

  // 3. Test "Ganti foto profil" button
  console.log('Testing Ganti foto profil button...');
  const gantiFotoBtn = page.getByRole('button', { name: 'Ganti foto profil' });
  await gantiFotoBtn.click();
  await page.waitForTimeout(1000);
  const fileInput = page.locator('input[type="file"]');
  console.log('File input count for avatar:', await fileInput.count());
  const fotoModal = page.locator('.modal, [role="dialog"]');
  console.log('Modal after clicking Ganti foto profil visible:', await fotoModal.isVisible());

  // 4. Test "Ubah Kata Sandi" modal validation
  console.log('Testing Ubah Kata Sandi...');
  await page.getByRole('button', { name: 'Batalkan' }).click();
  await page.waitForTimeout(500);

  await page.getByRole('button', { name: 'Ubah Kata Sandi' }).click();
  await page.waitForTimeout(1000);

  const sandiInputs = await page.locator('.modal input, [role="dialog"] input').all();
  console.log('Sandi modal input count:', sandiInputs.length);

  // Click submit in modal with empty fields
  const simpanSandiBtn = page.locator('.modal button:has-text("Simpan"), [role="dialog"] button:has-text("Simpan")');
  if (await simpanSandiBtn.isVisible()) {
    await simpanSandiBtn.click();
    await page.waitForTimeout(1000);
    const sandiErrors = await page.locator('.modal .error, .modal .text-red-500, [role="dialog"] [role="alert"]').allInnerTexts();
    console.log('Errors on empty password submit:', sandiErrors);
    await page.screenshot({ path: 'test-results/profil-sandi-empty-error.png' });
  }

  await browser.close();
}

testProfileValidation().catch(console.error);
