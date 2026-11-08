// New test: Register new account and perform province simulation
import { test, expect } from '@playwright/test';

// Utility to generate a random email
function randomEmail() {
  const timestamp = Date.now();
  return `testuser_${timestamp}@example.com`;
}

test('Register new account and complete province simulation', async ({ page }) => {
  const baseUrl = 'https://mrcln0305.xyz/';
  await page.goto(baseUrl);

  // ---- Registration ----
  await page.click('text=Daftar'); // adjust selector as needed
  await page.fill('input[name="email"]', randomEmail());
  await page.fill('input[name="username"]', `user${Date.now()}`);
  await page.fill('input[name="password"]', 'Password123!');
  await page.fill('input[name="confirmPassword"]', 'Password123!');
  await page.click('button:has-text("Daftar")');

  // Expect successful registration message or redirect to dashboard
  const registrationSuccess = await page.waitForSelector('text=Selamat datang', { timeout: 10000 }).catch(() => null);
  if (!registrationSuccess) {
    await page.screenshot({ path: 'screenshots/registration_failed.png' });
    test.fail(true, 'Registration did not succeed');
    return;
  }

  // ---- Login (if not automatically logged in) ----
  await page.goto(`${baseUrl}login`);
  await page.fill('input[name="email"]', await page.inputValue('input[name="email"]')).catch(() => {});
  // Assume after registration we are redirected; skip if already logged in

  // ---- Navigate to Materi Wajib ----
  await page.click('text=Materi Wajib');
  // Complete all required materi (placeholder, adapt to actual flow)
  const materiLinks = await page.$$('a.materi-item');
  for (const link of materiLinks) {
    await link.click();
    // Assume there's a "Selesai" button to finish each materi
    await page.click('button:has-text("Selesai")');
    await page.goBack();
  }

  // ---- Open Province Simulation ----
  await page.click('text=Simulasi Provinsi');
  // Example: select a province and answer a few questions
  await page.selectOption('select#province', 'Jawa Barat');
  await page.click('button:has-text("Mulai Simulasi")');

  // Perform a simple check that simulation page loads
  const simHeader = await page.waitForSelector('text=Simulasi Provinsi', { timeout: 10000 }).catch(() => null);
  if (!simHeader) {
    await page.screenshot({ path: 'screenshots/simulation_load_failed.png' });
    test.fail(true, 'Simulation page did not load');
    return;
  }

  // Example interaction: answer first question
  await page.fill('input[name="answer1"]', '42');
  await page.click('button:has-text("Submit")');

  // Verify result
  const result = await page.waitForSelector('text=Hasil Simulasi', { timeout: 10000 }).catch(() => null);
  if (!result) {
    await page.screenshot({ path: 'screenshots/simulation_result_failed.png' });
    test.fail(true, 'Simulation result not displayed');
  }
});
