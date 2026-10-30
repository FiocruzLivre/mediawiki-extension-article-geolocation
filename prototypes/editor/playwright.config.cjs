const { defineConfig, devices } = require('@playwright/test');
const chromiumExecutable = process.env.MAPA_CHROMIUM_EXECUTABLE;
module.exports=defineConfig({
testDir:'./tests',reporter:'list',timeout:15000,
use:{baseURL:'http://127.0.0.1:9876'},
projects:[
{name:'chromium',use:{...devices['Desktop Chrome'],launchOptions:chromiumExecutable?{executablePath:chromiumExecutable}:{}}},
{name:'firefox',use:{...devices['Desktop Firefox']}},
{name:'mobile',use:{...devices['Pixel 7'],launchOptions:chromiumExecutable?{executablePath:chromiumExecutable}:{}}}
],
webServer:{command:'python3 -m http.server 9876 --bind 127.0.0.1',url:'http://127.0.0.1:9876/demo.html',reuseExistingServer:false}
});