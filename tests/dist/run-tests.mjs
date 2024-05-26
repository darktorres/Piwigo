// https://stackoverflow.com/questions/51676159/puppeteer-console-log-how-to-look-inside-jshandleobject/66801550#66801550
// https://github.com/HTMLHint/HTMLHint
// TODO: delete piwigo config file and run install.php
// TODO: do a quick sync
import puppeteer from "puppeteer";
import fs from "fs-extra";
import path from "path";
import { gray, cyan, magenta, red, yellow } from "colorette";
import { execSync } from "child_process";
import { MultiBar, Presets } from "cli-progress";
const parallel = process.argv.includes("--parallel");
const save = process.argv.includes("--save");
const firstCommitArg = process.argv.find((arg) => arg.startsWith("--firstCommit="));
const firstCommit = firstCommitArg ? firstCommitArg.split("=")[1] : null;
const lastCommitArg = process.argv.find((arg) => arg.startsWith("--lastCommit="));
const lastCommit = lastCommitArg ? lastCommitArg.split("=")[1] : null;
const main = async () => {
    if (firstCommit && lastCommit) {
        if (firstCommit == lastCommit) {
            console.error("Please provide different commits.");
            process.exit(1);
        }
        // Get commit hashes to iterate over
        const commits = await getCommitHashes(firstCommit, lastCommit);
        let counter = 0;
        for (const commit of commits) {
            console.log(`Checking out commit: ${commit}`);
            execShellCommand(`git checkout ${commit}`);
            runComposerInstall();
            await runPuppeteerScript(++counter, await getCommitMessage(commit));
        }
        // Checkout back to the last commit or the branch you started from
        execShellCommand(`git checkout automated_testing`);
        process.exit(0);
    }
    else {
        await runPuppeteerScript(0, await getCommitMessage(''));
    }
};
main().catch((err) => console.error("Error during operation:", err));
// ===================================== END MAIN ===========================================
async function runPuppeteerScript(counter, commitMessage) {
    // Delete the _data folder before starting Puppeteer operations
    clearDirectoryExcept(path.resolve(import.meta.dirname, "../../_data"), path.resolve(import.meta.dirname, "../../_data/i")).catch((err) => console.error("Error during operation:", err));
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
        const pageLoadPromises = pages.map(async (url) => await loadPage(browser, url, progressBar, counter, commitMessage));
        await Promise.all(pageLoadPromises);
    }
    else {
        for (const url of pages) {
            await loadPage(browser, url, progressBar, counter, commitMessage);
        }
    }
    multiBar.update();
    multiBar.stop();
    await browser.close();
}
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
async function savePageContent(page, url, counter, commitMessage) {
    // TODO: add a 'ready' variable to pages to waitFor so all js has run
    // const watchDog = page.waitForFunction('window.status === "ready"');
    // await watchDog;
    const content = await page.content();
    const safeMessage = commitMessage.replace(/[<>:"\/\\|?*]+/g, ""); // Remove invalid characters
    const dirName = `${counter} ${safeMessage}`;
    const dirPath = path.join(import.meta.dirname, "/pages/", dirName);
    const filePath = path.join(dirPath, encodeURIComponent(url + ".html"));
    await fs.outputFile(filePath, content);
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
            console.log(`    at ${frame.url}:${(frame.lineNumber ?? 0) + 1}:${(frame.columnNumber ?? 0) + 1}`);
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
async function loadPage(browser, url, progressBar, counter, commitMessage) {
    try {
        const page = await browser.newPage();
        page
            .on("console", handleConsoleMessage)
            .on("pageerror", ({ message }) => console.log(red(message)))
            //.on('response', response => console.log(green(`${response.status()} ${response.url()}`)))
            .on("requestfailed", (request) => console.log(magenta(`${request.failure()?.errorText} ${request.url()}`)));
        // console.log(`\nLoaded ${url}`);
        const fullUrl = `http://localhost/Piwigo2/${url}`;
        await page.goto(fullUrl, { waitUntil: "networkidle0" });
        progressBar.increment();
        if (save) {
            await savePageContent(page, url, counter, commitMessage);
        }
        await page.close();
    }
    catch (error) {
        console.error(`Failed to load ${url}: ${error}`);
    }
}
async function execShellCommand(cmd) {
    console.log(`Command to run: ${cmd}`);
    try {
        const result = execSync(cmd, { stdio: ["inherit", "pipe"] });
        console.log(`Result: ${result}`);
        // Convert buffer to string and trim whitespace
        return result.toString().trim();
    }
    catch (error) {
        console.error(`Error executing command: ${cmd}`, error);
        process.exit(1);
    }
}
async function getCommitHashes(firstCommit, lastCommit) {
    const result = await execShellCommand(`git rev-list ${firstCommit}..${lastCommit}`);
    console.log(`rev-list is ${result}`);
    return result.split("\n").reverse(); // Reverse to process commits from first to last
}
async function getCommitMessage(commitHash) {
    const message = await execShellCommand(`git show -s --format=%s ${commitHash}`);
    console.log(message);
    return message;
}
async function runComposerInstall() {
    try {
        const workingDir = path.resolve(import.meta.dirname, "../..");
        const composerJsonPath = path.resolve(workingDir, "composer.json");
        if (!fs.existsSync(composerJsonPath)) {
            console.log("composer.json not found, skipping composer install.");
            return;
        }
        const output = await execShellCommand(`composer install --working-dir=${workingDir}`);
        console.log("Composer install output:", output);
    }
    catch (error) {
        console.error("Error in runComposerInstall:", error);
    }
}
