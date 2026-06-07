const fs = require('fs');
const puppeteer = require('puppeteer'); // v23.0.0 or later

(async () => {
    const browser = await puppeteer.launch();
    const page = await browser.newPage();
    const timeout = 5000;
    page.setDefaultTimeout(timeout);

    const lhApi = await import('lighthouse'); // v10.0.0 or later
    const flags = {
        screenEmulation: {
            disabled: true
        }
    }
    const config = lhApi.desktopConfig;
    const lhFlow = await lhApi.startFlow(page, {name: 'Recording 6/7/2026 at 5:00:22 PM', config, flags});
    {
        const targetPage = page;
        await targetPage.setViewport({
            width: 748,
            height: 665
        })
    }
    await lhFlow.startNavigation();
    {
        const targetPage = page;
        await targetPage.goto('https://qirimtatartili.app/en/tools/transliterate');
    }
    await lhFlow.endNavigation();
    await lhFlow.startTimespan();
    {
        const targetPage = page;
        await puppeteer.Locator.race([
            targetPage.locator('p.mt-2'),
            targetPage.locator('::-p-xpath(/html/body/main/div[3]/div/div[1]/p[1])'),
            targetPage.locator(':scope >>> p.mt-2'),
            targetPage.locator('::-p-text(Transliterate)')
        ])
            .setTimeout(timeout)
            .click({
              offset: {
                x: 600,
                y: 29,
              },
            });
    }
    {
        const targetPage = page;
        await targetPage.keyboard.down('Control');
    }
    {
        const targetPage = page;
        await targetPage.keyboard.down('Shift');
    }
    await lhFlow.endTimespan();
    await lhFlow.startNavigation();
    {
        const targetPage = page;
        const promises = [];
        const startWaitingForEvents = () => {
            promises.push(targetPage.waitForNavigation());
        }
        await targetPage.keyboard.down('К');
        await Promise.all(promises);
    }
    await lhFlow.endNavigation();
    await lhFlow.startTimespan();
    {
        const targetPage = page;
        await targetPage.keyboard.up('К');
    }
    {
        const targetPage = page;
        await puppeteer.Locator.race([
            targetPage.locator('::-p-aria(Enter text to transliterate)'),
            targetPage.locator('#_R_2av5ubsqivb_'),
            targetPage.locator('::-p-xpath(//*[@id=\\"_R_2av5ubsqivb_\\"])'),
            targetPage.locator(':scope >>> #_R_2av5ubsqivb_')
        ])
            .setTimeout(timeout)
            .click({
              offset: {
                x: 523.3333320617676,
                y: 46.33331298828125,
              },
            });
    }
    {
        const targetPage = page;
        await targetPage.keyboard.down('Alt');
    }
    {
        const targetPage = page;
        await targetPage.keyboard.down('Shift');
    }
    {
        const targetPage = page;
        await targetPage.keyboard.up('Alt');
    }
    {
        const targetPage = page;
        await targetPage.keyboard.up('Control');
    }
    {
        const targetPage = page;
        await targetPage.keyboard.up('Shift');
    }
    {
        const targetPage = page;
        await puppeteer.Locator.race([
            targetPage.locator('::-p-aria(Enter text to transliterate)'),
            targetPage.locator('#_R_2av5ubsqivb_'),
            targetPage.locator('::-p-xpath(//*[@id=\\"_R_2av5ubsqivb_\\"])'),
            targetPage.locator(':scope >>> #_R_2av5ubsqivb_')
        ])
            .setTimeout(timeout)
            .fill('');
    }
    {
        const targetPage = page;
        await targetPage.keyboard.down('Alt');
    }
    {
        const targetPage = page;
        await targetPage.keyboard.down('Shift');
    }
    {
        const targetPage = page;
        await targetPage.keyboard.up('Alt');
    }
    {
        const targetPage = page;
        await targetPage.keyboard.up('Shift');
    }
    {
        const targetPage = page;
        await targetPage.keyboard.down('Alt');
    }
    {
        const targetPage = page;
        await targetPage.keyboard.down('Shift');
    }
    {
        const targetPage = page;
        await targetPage.keyboard.up('Alt');
    }
    {
        const targetPage = page;
        await targetPage.keyboard.up('Control');
    }
    {
        const targetPage = page;
        await targetPage.keyboard.up('Shift');
    }
    {
        const targetPage = page;
        await puppeteer.Locator.race([
            targetPage.locator('::-p-aria(Enter text to transliterate)'),
            targetPage.locator('#_R_2av5ubsqivb_'),
            targetPage.locator('::-p-xpath(//*[@id=\\"_R_2av5ubsqivb_\\"])'),
            targetPage.locator(':scope >>> #_R_2av5ubsqivb_')
        ])
            .setTimeout(timeout)
            .fill('араба');
    }
    await lhFlow.endTimespan();
    const lhFlowReport = await lhFlow.generateReport();
    fs.writeFileSync(__dirname + '/flow.report.html', lhFlowReport)

    await browser.close();

})().catch(err => {
    console.error(err);
    process.exit(1);
});
