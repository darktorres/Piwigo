// https://stackoverflow.com/questions/51676159/puppeteer-console-log-how-to-look-inside-jshandleobject/66801550#66801550
// https://github.com/HTMLHint/HTMLHint
// TODO: delete piwigo config file and run install.php
// TODO: do a quick sync
import puppeteer from "puppeteer";
import fs from "fs-extra";
import path from "path";
import { gray, cyan, magenta, red, yellow } from "colorette";
import { execSync } from "child_process";
import { JSDOM } from "jsdom";
import { MultiBar, Presets } from "cli-progress";
const parallel = process.argv.includes("--parallel");
const firstCommitArg = process.argv.find((arg) => arg.startsWith("--firstCommit="));
const firstCommit = firstCommitArg ? firstCommitArg.split("=")[1] : null;
const lastCommitArg = process.argv.find((arg) => arg.startsWith("--lastCommit="));
const lastCommit = lastCommitArg ? lastCommitArg.split("=")[1] : null;
const runPuppeteerScript = async (commitDetails) => {
    // Delete the _data folder before starting Puppeteer operations
    clearDirectoryExcept(path.resolve(import.meta.dirname, "../../_data"), path.resolve(import.meta.dirname, "../../_data/i")).catch((err) => console.error("Error during operation:", err));
    await clearDirectoryExcept(path.resolve(import.meta.dirname, "./bef_pages")).catch((err) => console.error("Error during operation:", err));
    const browser = await puppeteer.launch({
        headless: true,
        defaultViewport: { width: 1280, height: 1024 },
        devtools: false,
    });
    const page = await browser.newPage();
    const pages = await readPageUrlsFromFile(path.join(import.meta.dirname, "pages.txt"));
    const multiBar = new MultiBar({ forceRedraw: true }, Presets.shades_classic);
    const totalSteps = pages.length;
    const progressBar = multiBar.create(totalSteps, 0);
    page
        .on("console", handleConsoleMessage)
        .on("pageerror", ({ message }) => console.log(red(message)))
        //.on('response', response => console.log(green(`${response.status()} ${response.url()}`)))
        .on("requestfailed", (request) => console.log(magenta(`${request.failure()?.errorText} ${request.url()}`)));
    // Enable request interception
    await page.setRequestInterception(true);
    page.on("request", handleRequest);
    //console.log(`\nLoaded http://localhost/Piwigo2/identification.php`);
    // Navigate to the login page
    await page.goto("http://localhost/Piwigo2/identification.php", { waitUntil: "networkidle0" });
    // Fill in the username and password
    await page.type("#username", "darktorres");
    await page.type("#password", "1234");
    // Click the login button and wait for navigation
    await page.click("#content > form > p:nth-child(2) > input[type=submit]:nth-child(2)");
    // Now you are logged in, perform any actions you need to do after login
    if (parallel) {
        const pageLoadPromises = pages.map(async (url) => await loadPage(browser, url, progressBar, commitDetails));
        await Promise.all(pageLoadPromises);
    }
    else {
        for (const url of pages) {
            // console.log(`\nLoaded ${url}`);
            const fullUrl = `http://localhost/Piwigo2/${url}`;
            await page.goto(fullUrl, { waitUntil: "networkidle0" });
            progressBar.increment();
            await savePageContent(page, url, commitDetails);
        }
    }
    multiBar.update();
    multiBar.stop();
    await browser.close();
};
const main = async () => {
    // Validate first and last commit arguments
    if (!firstCommit || !lastCommit) {
        console.error("Please provide --firstCommit and --lastCommit arguments.");
        process.exit(1);
    }
    // Get commit hashes to iterate over
    const commits = getCommitHashes(firstCommit, lastCommit);
    for (const commit of commits) {
        console.log(`Checking out commit: ${commit}`);
        execShellCommand(`git checkout ${commit}`);
        await runPuppeteerScript(getCommitDetails(commit));
    }
    // Checkout back to the last commit or the branch you started from
    execShellCommand(`git checkout ${lastCommit}`);
};
main().catch((err) => console.error("Error during operation:", err));
// ===================================== END MAIN ===========================================
async function readPageUrlsFromFile(filePath) {
    try {
        const content = await fs.readFile(filePath, "utf-8");
        return content
            .split("\n")
            .map((line) => line.trim())
            .filter((line) => line.length > 0 && !line.startsWith("//"));
    }
    catch (error) {
        console.error(`Error reading file ${filePath}: ${error}`);
        return [];
    }
}
async function sanitizeFilename(url) {
    return url.replace(/[^a-z0-9]/gi, "_").toLowerCase();
}
async function savePageContent(page, url, commitDetails) {
    const content = await page.content();
    const safeMessage = commitDetails.message.replace(/[<>:"\/\\|?*]+/g, ""); // Remove invalid characters
    const dirName = `${commitDetails.date} ${safeMessage}`;
    const dirPath = path.join(import.meta.dirname, dirName);
    // await fs.ensureDir(dirPath);
    const filePath = path.join(dirPath, encodeURIComponent(url));
    await fs.outputFile(filePath, content);
    // const sanitizedUrl = await sanitizeFilename(url);
    // const savedFilename = path.join(import.meta.dirname, `./bef_pages/${sanitizedUrl}.html`);
    // const currentFilename = path.join(import.meta.dirname, `./aft_pages/${sanitizedUrl}.html`);
    // // TODO: add a 'ready' variable to pages to waitFor so all js has run
    // // const watchDog = page.waitForFunction('window.status === "ready"');
    // // await watchDog;
    // const content = await page.content();
    // if (!fs.existsSync(path.join(import.meta.dirname, "./bef_pages"))) {
    //   fs.mkdirSync(path.join(import.meta.dirname, "./bef_pages"));
    // }
    // fs.writeFileSync(savedFilename, content);
}
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
    if (!text.startsWith("PHP:")) {
        const stackTrace = message.stackTrace();
        for (const frame of stackTrace) {
            console.log(`    at ${frame.url}:${frame.lineNumber}:${frame.columnNumber}`);
        }
    }
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
async function fetchHTMLContent(filePath) {
    const content = await fs.readFile(filePath, "utf-8");
    const dom = new JSDOM(content);
    return dom.window.document.documentElement;
}
async function elementsAreEquivalent(elem1, elem2) {
    if (elem1.tagName !== elem2.tagName) {
        return false;
    }
    const attrs1 = Array.from(elem1.attributes)
        .map((attr) => `${attr.name}="${attr.value}"`)
        .sort();
    const attrs2 = Array.from(elem2.attributes)
        .map((attr) => `${attr.name}="${attr.value}"`)
        .sort();
    if (JSON.stringify(attrs1) !== JSON.stringify(attrs2)) {
        return false;
    }
    const children1 = elem1.children;
    const children2 = elem2.children;
    if (children1.length !== children2.length) {
        return false;
    }
    for (let i = 0; i < children1.length; i++) {
        const child1 = children1[i];
        const child2 = children2[i];
        if (!child1 || !child2) {
            return false;
        }
        const equivalent = await elementsAreEquivalent(child1, child2);
        if (!equivalent) {
            return false;
        }
    }
    return true;
}
async function compareHTMLFiles(filePath1, filePath2) {
    const doc1 = await fetchHTMLContent(filePath1);
    const doc2 = await fetchHTMLContent(filePath2);
    return await elementsAreEquivalent(doc1, doc2);
}
async function loadPage(browser, url, progressBar, commitDetails) {
    try {
        const page = await browser.newPage();
        page
            .on("console", handleConsoleMessage)
            .on("pageerror", ({ message }) => console.log(red(message)))
            //.on('response', response => console.log(green(`${response.status()} ${response.url()}`)))
            .on("requestfailed", (request) => console.log(magenta(`${request.failure()?.errorText} ${request.url()}`)));
        const fullUrl = `http://localhost/Piwigo2/${url}`;
        await page.goto(fullUrl, { waitUntil: "networkidle0" });
        progressBar.increment();
        await savePageContent(page, url, commitDetails);
        await page.close();
    }
    catch (error) {
        console.error(`Failed to load ${url}: ${error}`);
    }
}
// Function to execute shell commands
const execShellCommand = (cmd) => {
    try {
        return execSync(cmd, { stdio: "inherit" }).toString().trim();
    }
    catch (error) {
        console.error(`Error executing command: ${cmd}`, error);
        process.exit(1);
    }
};
// Function to get all commit hashes between firstCommit and lastCommit
const getCommitHashes = (firstCommit, lastCommit) => {
    const result = execSync(`git rev-list ${firstCommit}..${lastCommit}`).toString().trim();
    return result.split("\n").reverse(); // Reverse to process commits from first to last
};
// Function to get commit date and message
const getCommitDetails = (commitHash) => {
    const date = execShellCommand(`git show -s --format=%ci ${commitHash}`);
    const message = execShellCommand(`git show -s --format=%s ${commitHash}`);
    return { date, message };
};
