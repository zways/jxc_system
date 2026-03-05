const http = require('http');
const https = require('https');
const fs = require('fs');

// Helper function to make HTTP requests
function makeRequest(options, postData = null) {
    return new Promise((resolve, reject) => {
        const protocol = options.protocol === 'https:' ? https : http;
        const req = protocol.request(options, (res) => {
            let data = '';
            
            res.on('data', (chunk) => {
                data += chunk;
            });
            
            res.on('end', () => {
                resolve({
                    statusCode: res.statusCode,
                    headers: res.headers,
                    body: data
                });
            });
        });
        
        req.on('error', (error) => {
            reject(error);
        });
        
        if (postData) {
            req.write(postData);
        }
        
        req.end();
    });
}

async function testApp() {
    const baseUrl = 'http://localhost:8002';
    const report = {
        timestamp: new Date().toISOString(),
        tests: []
    };
    
    console.log('='.repeat(80));
    console.log('Testing 进销存管理系统 (Inventory Management System)');
    console.log('='.repeat(80));
    console.log();
    
    // STEP 1: Test Login Page
    console.log('STEP 1: Testing Login Page');
    console.log('-'.repeat(80));
    
    try {
        const loginPageResponse = await makeRequest({
            hostname: 'localhost',
            port: 8002,
            path: '/login',
            method: 'GET',
            headers: {
                'User-Agent': 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36'
            }
        });
        
        console.log(`✓ Login page loaded successfully (Status: ${loginPageResponse.statusCode})`);
        
        // Save login page HTML
        fs.writeFileSync('login_page.html', loginPageResponse.body);
        console.log('✓ Login page HTML saved to login_page.html');
        
        // Check for login form elements
        const hasUsernameField = loginPageResponse.body.includes('username') || 
                                 loginPageResponse.body.includes('用户名') ||
                                 loginPageResponse.body.includes('账号');
        const hasPasswordField = loginPageResponse.body.includes('password') || 
                                 loginPageResponse.body.includes('密码');
        const hasLoginButton = loginPageResponse.body.includes('登录') || 
                              loginPageResponse.body.includes('login');
        
        console.log(`  - Username field present: ${hasUsernameField ? '✓' : '✗'}`);
        console.log(`  - Password field present: ${hasPasswordField ? '✓' : '✗'}`);
        console.log(`  - Login button present: ${hasLoginButton ? '✓' : '✗'}`);
        
        report.tests.push({
            step: 'Login Page Load',
            status: 'success',
            statusCode: loginPageResponse.statusCode,
            details: {
                hasUsernameField,
                hasPasswordField,
                hasLoginButton
            }
        });
        
    } catch (error) {
        console.log(`✗ Failed to load login page: ${error.message}`);
        report.tests.push({
            step: 'Login Page Load',
            status: 'failed',
            error: error.message
        });
    }
    
    console.log();
    
    // STEP 2: Test Login API
    console.log('STEP 2: Testing Login API');
    console.log('-'.repeat(80));
    
    try {
        // First, get CSRF token from login page
        const loginPageResponse = await makeRequest({
            hostname: 'localhost',
            port: 8002,
            path: '/login',
            method: 'GET'
        });
        
        // Extract CSRF token
        const csrfMatch = loginPageResponse.body.match(/csrf[_-]?token["']?\s*[:=]\s*["']([^"']+)["']/i) ||
                         loginPageResponse.body.match(/<meta name="csrf-token" content="([^"]+)"/i) ||
                         loginPageResponse.body.match(/_token["']?\s*[:=]\s*["']([^"']+)["']/i);
        
        const csrfToken = csrfMatch ? csrfMatch[1] : '';
        console.log(`  CSRF Token: ${csrfToken ? '✓ Found' : '✗ Not found'}`);
        
        // Extract cookies
        const cookies = loginPageResponse.headers['set-cookie'] || [];
        const cookieString = cookies.map(c => c.split(';')[0]).join('; ');
        
        // Attempt login
        const loginData = JSON.stringify({
            username: 'admin',
            password: 'password',
            device_name: 'web'
        });
        
        const loginResponse = await makeRequest({
            hostname: 'localhost',
            port: 8002,
            path: '/api/v1/auth/login',
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Content-Length': Buffer.byteLength(loginData),
                'Cookie': cookieString,
                'X-CSRF-TOKEN': csrfToken,
                'Accept': 'application/json',
                'User-Agent': 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36'
            }
        }, loginData);
        
        console.log(`  Login API Response Status: ${loginResponse.statusCode}`);
        
        let loginResult;
        try {
            loginResult = JSON.parse(loginResponse.body);
            console.log(`  Login Response:`, JSON.stringify(loginResult, null, 2));
        } catch (e) {
            console.log(`  Login Response (raw):`, loginResponse.body.substring(0, 500));
        }
        
        // Check if login was successful
        const loginSuccess = loginResponse.statusCode === 200 || 
                           (loginResult && (loginResult.success || loginResult.token));
        
        console.log(`  Login Status: ${loginSuccess ? '✓ Success' : '✗ Failed'}`);
        
        report.tests.push({
            step: 'Login API',
            status: loginSuccess ? 'success' : 'failed',
            statusCode: loginResponse.statusCode,
            response: loginResult
        });
        
        // If login successful, test dashboard
        if (loginSuccess) {
            console.log();
            console.log('STEP 3: Testing Dashboard');
            console.log('-'.repeat(80));
            
            // Extract auth token or session cookie
            const authCookies = loginResponse.headers['set-cookie'] || [];
            const authCookieString = [...cookies, ...authCookies].map(c => c.split(';')[0]).join('; ');
            const authToken = loginResult?.token || loginResult?.access_token || '';
            
            const dashboardResponse = await makeRequest({
                hostname: 'localhost',
                port: 8002,
                path: '/',
                method: 'GET',
                headers: {
                    'Cookie': authCookieString,
                    'Authorization': authToken ? `Bearer ${authToken}` : '',
                    'User-Agent': 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36'
                }
            });
            
            console.log(`✓ Dashboard loaded (Status: ${dashboardResponse.statusCode})`);
            
            // Save dashboard HTML
            fs.writeFileSync('dashboard_page.html', dashboardResponse.body);
            console.log('✓ Dashboard page HTML saved to dashboard_page.html');
            
            // Analyze dashboard content
            const hasCharts = dashboardResponse.body.includes('chart') || 
                            dashboardResponse.body.includes('echarts') ||
                            dashboardResponse.body.includes('canvas');
            const hasSidebar = dashboardResponse.body.includes('sidebar') || 
                             dashboardResponse.body.includes('menu') ||
                             dashboardResponse.body.includes('导航');
            const hasStats = dashboardResponse.body.includes('统计') || 
                           dashboardResponse.body.includes('dashboard');
            
            console.log(`  - Charts present: ${hasCharts ? '✓' : '✗'}`);
            console.log(`  - Sidebar present: ${hasSidebar ? '✓' : '✗'}`);
            console.log(`  - Statistics present: ${hasStats ? '✓' : '✗'}`);
            
            // Extract menu items from HTML
            console.log();
            console.log('STEP 4: Analyzing Sidebar Menu');
            console.log('-'.repeat(80));
            
            // Common menu item patterns
            const menuPatterns = [
                /采购管理|Purchase/gi,
                /销售管理|Sales/gi,
                /库存管理|Inventory/gi,
                /财务管理|Financial/gi,
                /基础设置|Basic Settings/gi,
                /系统管理|System/gi,
                /报表|Reports/gi,
                /仓库|Warehouse/gi,
                /供应商|Supplier/gi,
                /客户|Customer/gi,
                /商品|Product/gi,
                /用户|User/gi,
                /角色|Role/gi,
                /部门|Department/gi
            ];
            
            const foundMenuItems = [];
            for (const pattern of menuPatterns) {
                const matches = dashboardResponse.body.match(pattern);
                if (matches) {
                    foundMenuItems.push(...new Set(matches));
                }
            }
            
            console.log('  Menu items found:');
            foundMenuItems.forEach(item => {
                console.log(`    - ${item}`);
            });
            
            report.tests.push({
                step: 'Dashboard',
                status: 'success',
                statusCode: dashboardResponse.statusCode,
                details: {
                    hasCharts,
                    hasSidebar,
                    hasStats,
                    menuItems: foundMenuItems
                }
            });
        }
        
    } catch (error) {
        console.log(`✗ Failed to test login: ${error.message}`);
        report.tests.push({
            step: 'Login API',
            status: 'failed',
            error: error.message
        });
    }
    
    console.log();
    console.log('='.repeat(80));
    console.log('TEST SUMMARY');
    console.log('='.repeat(80));
    
    const successCount = report.tests.filter(t => t.status === 'success').length;
    const failCount = report.tests.filter(t => t.status === 'failed').length;
    
    console.log(`Total Tests: ${report.tests.length}`);
    console.log(`Passed: ${successCount}`);
    console.log(`Failed: ${failCount}`);
    console.log();
    
    // Save full report
    fs.writeFileSync('test_report.json', JSON.stringify(report, null, 2));
    console.log('✓ Full test report saved to test_report.json');
    console.log();
}

// Run tests
testApp().catch(console.error);
