const { chromium } = require('@playwright/test');

async function testSchoolSave() {
  const browser = await chromium.launch({ headless: true });
  const page = await browser.newPage();
  await page.goto('https://mrcln0305.xyz/login');
  await page.locator('#email').fill('testqa6@gmail.com');
  await page.locator('#password').fill('test_12345678');
  await page.getByRole('button', { name: 'Masuk ke Akun' }).click();
  await page.waitForURL(/\/siswa/);
  await page.goto('https://mrcln0305.xyz/siswa/profil');

  await page.getByRole('button', { name: /Ubah Profil/i }).click();
  await page.locator('#sekolah').fill('SMA Negeri 1 Malang');
  await page.locator('#kelas').fill('11');

  page.on('request', r => console.log('REQ:', r.method(), r.url(), r.postData()));
  page.on('response', async r => {
    if (r.url().includes('/api/')) {
      console.log('RESP:', r.status(), r.url());
      try { console.log('BODY:', await r.text()); } catch (e) {}
    }
  });

  await page.getByRole('button', { name: 'Simpan Perubahan' }).click();
  await page.waitForTimeout(2000);

  // Reload page
  console.log('Reloading page to check persistence...');
  await page.reload({ waitUntil: 'networkidle' });
  await page.waitForTimeout(1000);

  const sekolahVal = await page.locator('#sekolah').inputValue();
  const kelasVal = await page.locator('#kelas').inputValue();
  console.log('After reload - Sekolah:', sekolahVal, 'Kelas:', kelasVal);

  await browser.close();
}

testSchoolSave().catch(console.error);
