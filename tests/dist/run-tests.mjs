// https://stackoverflow.com/questions/51676159/puppeteer-console-log-how-to-look-inside-jshandleobject/66801550#66801550
// https://github.com/HTMLHint/HTMLHint
import puppeteer from "puppeteer";
import fs from "fs-extra";
import path from "path";
import { gray, cyan, magenta, red, yellow } from "colorette";
// import { execSync } from "child_process";
// import { MultiBar, Presets, SingleBar } from "cli-progress";
const main = async () => {
    await runPuppeteerScript();
};
main().catch((err) => console.error("Error during operation:", err));
// ===================================== END MAIN ===========================================
async function runPuppeteerScript() {
    // Delete the _data folder before starting Puppeteer operations
    clearDirectoryExcept(path.resolve(import.meta.dirname, "../../_data"), path.resolve(import.meta.dirname, "../../_data/i")).catch((err) => console.error("Error during operation:", err));
    const browser = await puppeteer.launch({
        headless: false,
        defaultViewport: { width: 1280, height: 1024 },
        devtools: true,
        // args: ["--start-maximized"],
    });
    const pages = await browser.pages();
    const page = pages[0];
    // Set Xdebug session cookie if necessary
    await page.setCookie({
        name: 'XDEBUG_SESSION',
        value: 'VSCODE',
        domain: 'localhost'
    });
    page.setDefaultTimeout(10000);
    page
        .on("console", handleConsoleMessage)
        .on("pageerror", ({ message }) => console.log(red(message)))
        //.on('response', response => console.log(green(`${response.status()} ${response.url()}`)))
        .on("requestfailed", (request) => console.log(magenta(`${request.failure()?.errorText} ${request.url()}`)));
    // Enable request interception
    await page.setRequestInterception(true);
    page.on("request", handleRequest);
    await fs.remove(path.resolve(import.meta.dirname, "../../local/config/database.inc.php"));
    // Navigate to the install page
    await page.goto("http://localhost/piwigo2/install.php", { waitUntil: "networkidle0" });
    await page.waitForSelector("#content > form > fieldset:nth-child(2) > table > tbody > tr:nth-child(2) > td:nth-child(2) > input[type=text]");
    // Database config
    // dbuser
    // await page.type("#content > form > fieldset:nth-child(2) > table > tbody > tr:nth-child(2) > td:nth-child(2) > input[type=text]", "root");
    // dbpassword
    // await page.type("#content > form > fieldset:nth-child(2) > table > tbody > tr:nth-child(3) > td:nth-child(2) > input[type=password]", "1234");
    // dbname
    // await page.type("#content > form > fieldset:nth-child(2) > table > tbody > tr:nth-child(4) > td:nth-child(2) > input[type=text]", "piwigo2");
    // dbprefix
    // await page.focus("#content > form > fieldset:nth-child(2) > table > tbody > tr:nth-child(5) > td:nth-child(2) > input[type=text]");
    // Select all text and delete it
    // await page.keyboard.down("Control");
    // await page.keyboard.press("A");
    // await page.keyboard.up("Control");
    // await page.keyboard.press("Backspace");
    // Admin config
    // await page.type("#content > form > fieldset:nth-child(3) > table > tbody > tr:nth-child(1) > td:nth-child(2) > input[type=text]", "darktorres");
    // await page.type("#content > form > fieldset:nth-child(3) > table > tbody > tr:nth-child(2) > td:nth-child(2) > input[type=password]", "1234");
    // await page.type("#content > form > fieldset:nth-child(3) > table > tbody > tr:nth-child(3) > td:nth-child(2) > input[type=password]", "1234");
    // await page.type("#admin_mail", "torres.dark@gmail.com");
    // subscribe email
    // await page.click("#content > form > fieldset:nth-child(3) > table > tbody > tr:nth-child(5) > td:nth-child(2) > label:nth-child(1) > input[type=checkbox]");
    // send email with settings
    // await page.click("#content > form > fieldset:nth-child(3) > table > tbody > tr:nth-child(5) > td:nth-child(2) > label:nth-child(3) > input[type=checkbox]");
    // install button
    await page.click("#content > form > div > input");
    // Admin pages
    // go to homepage
    await page.waitForSelector("#content > p > a");
    await page.click("#content > p > a");
    // deactivate empty gallery message
    await page.waitForSelector("#deactivate > a");
    await page.click("#deactivate > a");
    // admin button
    await page.waitForSelector("#menubar > dl:nth-child(7) > dt > a:nth-child(4)");
    await page.click("#menubar > dl:nth-child(7) > dt > a:nth-child(4)");
    // hide subscribe to newsletter button
    await page.waitForSelector("#content > p > span > a.newsletter-hide");
    await page.click("#content > p > span > a.newsletter-hide");
    // do a quick sync
    await page.waitForSelector("#content > p > a");
    await page.click("#content > p > a");
    const sleep = (ms) => new Promise((res) => setTimeout(res, ms));
    // click on 'Photos' menu
    await page.waitForSelector("#menubar > dl:nth-child(2) > dt > span");
    await page.click("#menubar > dl:nth-child(2) > dt > span");
    await sleep(1500);
    // click on 'Add' button
    await page.waitForSelector("#menubar > dl:nth-child(2) > dd > ul > li:nth-child(1) > a");
    await page.click("#menubar > dl:nth-child(2) > dd > ul > li:nth-child(1) > a");
    await sleep(1500);
    // click on 'Tags' button
    await page.waitForSelector("#menubar > dl:nth-child(2) > dd > ul > li:nth-child(2) > a");
    await page.click("#menubar > dl:nth-child(2) > dd > ul > li:nth-child(2) > a");
    await sleep(1500);
    // click on 'Recent photos'
    await page.waitForSelector("#menubar > dl:nth-child(2) > dd > ul > li:nth-child(3) > a");
    await page.click("#menubar > dl:nth-child(2) > dd > ul > li:nth-child(3) > a");
    await sleep(1500);
    // click on 'Batch Manager'
    await page.waitForSelector("#menubar > dl:nth-child(2) > dd > ul > li:nth-child(4) > a");
    await page.click("#menubar > dl:nth-child(2) > dd > ul > li:nth-child(4) > a");
    await sleep(1500);
    // click on 'Caddie'
    await page.waitForSelector("#menubar > dl:nth-child(2) > dd > ul > li:nth-child(5) > a");
    await page.click("#menubar > dl:nth-child(2) > dd > ul > li:nth-child(5) > a");
    await sleep(1500);
    // click on 'Albums' menu
    await page.waitForSelector("#menubar > dl:nth-child(3) > dt > span");
    await page.click("#menubar > dl:nth-child(3) > dt > span");
    await sleep(1500);
    // click on 'Manage' button
    await page.waitForSelector("#menubar > dl:nth-child(3) > dd > ul > li:nth-child(1) > a");
    await page.click("#menubar > dl:nth-child(3) > dd > ul > li:nth-child(1) > a");
    await sleep(1500);
    // click on 'Properties'
    await page.waitForSelector("#menubar > dl:nth-child(3) > dd > ul > li:nth-child(2) > a");
    await page.click("#menubar > dl:nth-child(3) > dd > ul > li:nth-child(2) > a");
    await sleep(1500);
    // click on 'Users' menu
    await page.waitForSelector("#menubar > dl:nth-child(4) > dt > span");
    await page.click("#menubar > dl:nth-child(4) > dt > span");
    await sleep(1500);
    // click on 'Manage' button
    await page.waitForSelector("#menubar > dl:nth-child(4) > dd > ul > li:nth-child(1) > a");
    await page.click("#menubar > dl:nth-child(4) > dd > ul > li:nth-child(1) > a");
    await sleep(1500);
    // click on 'Groups' button
    await page.waitForSelector("#menubar > dl:nth-child(4) > dd > ul > li:nth-child(2) > a");
    await page.click("#menubar > dl:nth-child(4) > dd > ul > li:nth-child(2) > a");
    await sleep(1500);
    // click on 'Notification' button
    await page.waitForSelector("#menubar > dl:nth-child(4) > dd > ul > li:nth-child(3) > a");
    await page.click("#menubar > dl:nth-child(4) > dd > ul > li:nth-child(3) > a");
    await sleep(1500);
    // click on 'Plugins' menu
    await page.waitForSelector("#menubar > dl:nth-child(5) > dt > a > span");
    await page.click("#menubar > dl:nth-child(5) > dt > a > span");
    await sleep(1500);
    // click on 'Tools' menu
    await page.waitForSelector("#menubar > dl:nth-child(6) > dt > span");
    await page.click("#menubar > dl:nth-child(6) > dt > span");
    await sleep(1500);
    // click on 'Synchronize' button
    await page.waitForSelector("#menubar > dl:nth-child(6) > dd > ul > li:nth-child(1) > a");
    await page.click("#menubar > dl:nth-child(6) > dd > ul > li:nth-child(1) > a");
    await sleep(1500);
    // click on 'History' button
    await page.waitForSelector("#menubar > dl:nth-child(6) > dd > ul > li:nth-child(2) > a");
    await page.click("#menubar > dl:nth-child(6) > dd > ul > li:nth-child(2) > a");
    await sleep(1500);
    // click on 'Maintenance' button
    await page.waitForSelector("#menubar > dl:nth-child(6) > dd > ul > li:nth-child(3) > a");
    await page.click("#menubar > dl:nth-child(6) > dd > ul > li:nth-child(3) > a");
    await sleep(1500);
    // click on 'Updates' button
    await page.waitForSelector("#menubar > dl:nth-child(6) > dd > ul > li:nth-child(4) > a");
    await page.click("#menubar > dl:nth-child(6) > dd > ul > li:nth-child(4) > a");
    await sleep(1500);
    // click on 'Configuration' menu
    await page.waitForSelector("#menubar > dl:nth-child(7) > dt > span");
    await page.click("#menubar > dl:nth-child(7) > dt > span");
    await sleep(1500);
    // click on 'Options' button
    await page.waitForSelector("#menubar > dl:nth-child(7) > dd > ul > li:nth-child(1) > a");
    await page.click("#menubar > dl:nth-child(7) > dd > ul > li:nth-child(1) > a");
    await sleep(1500);
    // click on 'Menus' button
    await page.waitForSelector("#menubar > dl:nth-child(7) > dd > ul > li:nth-child(2) > a");
    await page.click("#menubar > dl:nth-child(7) > dd > ul > li:nth-child(2) > a");
    await sleep(1500);
    // click on 'Languages' button
    await page.waitForSelector("#menubar > dl:nth-child(7) > dd > ul > li:nth-child(3) > a");
    await page.click("#menubar > dl:nth-child(7) > dd > ul > li:nth-child(3) > a");
    await sleep(1500);
    // click on 'Themes' button
    await page.waitForSelector("#menubar > dl:nth-child(7) > dd > ul > li:nth-child(4) > a");
    await page.click("#menubar > dl:nth-child(7) > dd > ul > li:nth-child(4) > a");
    await sleep(1500);
    // Gallery
    // go to homepage
    await page.waitForSelector("#pwgHead > a");
    await page.click("#pwgHead > a");
    await sleep(1500);
    // open album
    await page.waitForSelector("#rv-at > li > a");
    await page.click("#rv-at > li > a");
    await sleep(1500);
    // open image
    await page.waitForSelector("#thumbnails > li:nth-child(1) > a");
    await page.click("#thumbnails > li:nth-child(1) > a");
    await sleep(1500);
    // const pages: string[] = await readPageUrlsFromFile(path.join(import.meta.dirname, "pages.txt"));
    // if (parallel) {
    //   const pageLoadPromises = pages.map(async (url) => await loadPage(browser, url, progressBar, counter, commitMessage));
    //   await Promise.all(pageLoadPromises);
    // } else {
    //   for (const url of pages) {
    //     await loadPage(browser, url, progressBar, counter, commitMessage);
    //   }
    // }
    // multiBar.update();
    // multiBar.stop();
    await browser.close();
}
// async function readPageUrlsFromFile(filePath: string): Promise<string[]> {
//   try {
//     const content = await fs.readFile(filePath, "utf-8");
//     return content
//       .split("\n")
//       .map((line) => line.trim())
//       .filter((line) => line.length > 0 && !line.startsWith("//"));
//   } catch (error) {
//     console.error(`Error reading file ${filePath}: ${error}`);
//     return [];
//   }
// }
async function handleRequest(interceptedRequest) {
    if (interceptedRequest.method() === "POST") {
        // Uncomment to log POST data
        //console.log('POST Data:', interceptedRequest.postData());
    }
    interceptedRequest.continue();
}
async function handleConsoleMessage(message) {
    const type = message.type();
    const text = message.text();
    if (text.startsWith("JQMIGRATE: Migrate is installed with logging active")) {
        return;
    }
    const colors = {
        log: gray,
        info: cyan,
        error: red,
        warn: yellow,
    };
    const color = colors[type] || magenta;
    if (type !== "trace") {
        console.log(color(`${type.toUpperCase()} ${text}`));
    }
    // Handle JSHandle@object
    for (const arg of message.args()) {
        if (arg.toString().startsWith("JSHandle@object")) {
            try {
                const value = await arg.jsonValue();
                console.log(value);
            }
            catch (error) {
                console.log("***" + arg.toString());
            }
        }
    }
    // Don't print stacktrace for PHP messages since they already have it inline
    // if (!text.startsWith("PHP:")) {
    const stackTrace = message.stackTrace();
    for (const frame of stackTrace) {
        console.log(`    at ${frame.url}:${(frame.lineNumber ?? 0) + 1}:${(frame.columnNumber ?? 0) + 1}`);
    }
    // }
    console.log("");
}
async function clearDirectoryExcept(dir, exclude) {
    const dirExists = await fs.pathExists(dir);
    if (!dirExists) {
        return;
    }
    const items = await fs.readdir(dir);
    for (const item of items) {
        const itemPath = path.join(dir, item);
        if (itemPath !== exclude) {
            await fs.remove(itemPath);
        }
    }
}
// async function loadPage(browser: Browser, url: string, progressBar: SingleBar, counter: number, commitMessage: string): Promise<void> {
//   try {
//     const page: Page = await browser.newPage();
//     page
//       .on("console", handleConsoleMessage)
//       .on("pageerror", ({ message }) => console.log(red(message)))
//       //.on('response', response => console.log(green(`${response.status()} ${response.url()}`)))
//       .on("requestfailed", (request) => console.log(magenta(`${request.failure()?.errorText} ${request.url()}`)));
//     // console.log(`\nLoaded ${url}`);
//     const fullUrl = `http://localhost/piwigo2/${url}`;
//     await page.goto(fullUrl, { waitUntil: "networkidle0" });
//     progressBar.increment();
//     await page.close();
//   } catch (error) {
//     console.error(`Failed to load ${url}: ${error}`);
//   }
// }
