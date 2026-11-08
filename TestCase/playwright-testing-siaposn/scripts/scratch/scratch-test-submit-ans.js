const { chromium } = require('@playwright/test');

async function testSubmitAnswered() {
  const browser = await chromium.launch({ headless: true });
  const page = await browser.newPage();
  page.on('request', r => {
    if (r.url().includes('/api/')) console.log('REQ:', r.method(), r.url(), r.postData());
  });
  page.on('response', async r => {
    if (r.url().includes('/api/')) console.log('RESP:', r.status(), r.url());
  });

  await page.goto('https://mrcln0305.xyz/login');
  await page.locator('#email').fill('testqa6@gmail.com');
  await page.locator('#password').fill('test_12345678');
  await page.getByRole('button', { name: 'Masuk ke Akun' }).click();
  await page.waitForURL(/\/siswa/);
  await page.goto('https://mrcln0305.xyz/siswa/simulasi/ujian/1');
  await page.waitForTimeout(2000);

  // Click an option on question 1
  const opsiA = page.locator('button.opsi, button:has-text("a.")').first();
  if (await opsiA.isVisible()) {
    console.log('Selecting option A on question 1...');
    await opsiA.click();
    await page.waitForTimeout(1000);
  }

  // Go to question 30
  await page.locator('button.nomer:has-text("30")').click();
  await page.waitForTimeout(500);

  // Click Selesai
  await page.getByRole('button', { name: /Selesai/i }).click();
  await page.waitForTimeout(1000);

  const modalText = await page.locator('.modal, [role="dialog"]').innerText();
  console.log('Modal text after answering 1 question:\n', modalText);

  const kumpulkanBtn = page.locator('.modal button:has-text("Kumpulkan")');
  console.log('Kumpulkan button count:', await kumpulkanBtn.count());
  console.log('Kumpulkan button text:', await kumpulkanBtn.allInnerTexts());
  console.log('Kumpulkan button disabled:', await kumpulkanBtn.first().isDisabled());

  await kumpulkanBtn.first().click();
  await page.waitForTimeout(3000);
  console.log('URL after clicking kumpulkan:', page.url());

  await browser.close();
}

testSubmitAnswered().catch(console.error);
