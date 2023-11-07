const { test, expect } = require("@playwright/test")

test.describe('test for login into gatherPress admin side and verify the event element', ()=>{
    test.beforeEach(async({page})=>{
        await page.setViewportSize({ width: 1920, height: 720 });
        await page.waitForLoadState('networkidle')
        
    })

    test(' Verify that the Event menu item are preloaded after clicking Add New button', async({page})=>{

        page.goto('https://develop.gatherpress.org/wp-login.php', {timeout:120000});
       
       
        await page.getByLabel('Username or Email Address').isVisible();
        await page.getByLabel('Username or Email Address').fill('testuser1');

        await page.getByLabel('Password', { exact: true }).isVisible();
        await page.getByLabel('Password', { exact: true }).fill('zm86079&volj&!R5maIWEYX4');

        await page.getByRole('button', { name: 'Log In' }).click();

        await page.getByRole('link', { name: 'Events', exact: true }).click();
        await page.screenshot({path:'event-page.png'})

        await page.locator('#wpbody-content').getByRole('link', { name: 'Add New' }).click();

        await page.getByLabel('Document Overview').click();

        await page.getByLabel('List View').locator('div').nth(1).isVisible()
        await page.screenshot({path:'add-new-event.png'})

    })

    
})