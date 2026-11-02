/**
 * Chụp ảnh demo toàn bộ tính năng Casino Portal (Chrome headless + CDP).
 *
 * Chạy:  node tools/capture-screenshots.js
 * Output: docs/screenshots/*.png
 *
 * Ghi chú: script tự đăng nhập bằng tài khoản admin trong .env (ADMIN_USER / ADMIN_PASS)
 * nếu có, để chụp được các trang quản trị.
 */
const { spawn } = require('child_process');
const http = require('http');
const fs = require('fs');
const path = require('path');
const net = require('net');
const WebSocket = require('ws');

const CHROME = 'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe';
const BASE = process.env.BASE_URL || 'http://127.0.0.1:8000';
const OUT = path.join(__dirname, '..', 'docs', 'screenshots');
const PROFILE = path.join(process.env.TEMP || '.', 'casino-shot-profile');

// đọc .env để lấy thông tin đăng nhập admin
function readEnv() {
  const f = path.join(__dirname, '..', '.env');
  const out = {};
  try {
    fs.readFileSync(f, 'utf8').split(/\r?\n/).forEach((l) => {
      const m = l.match(/^\s*([A-Z0-9_]+)\s*=\s*(.*)$/);
      if (m) out[m[1]] = m[2].trim();
    });
  } catch (e) {}
  return out;
}

const ENV = readEnv();
const ADMIN_USER = process.env.ADMIN_USER || ENV.ADMIN_USER || 'admin';
const ADMIN_PASS = process.env.ADMIN_PASS || ENV.ADMIN_PASS || 'admin123';

const GUEST_PAGES = [
  ['01-trang-chu',          '/',                    'Trang chủ - Showcase PG SOFT 1:1'],
  ['02-kho-game',           '/games',               'Kho game - danh sách theo sảnh'],
  ['03-chi-tiet-game',      '/games/crypto-gold',   'Chi tiết game + nút mở game'],
  ['04-sanh-game',          '/lobbies',             'Danh sách sảnh (lobby)'],
  ['05-sanh-pgsoft',        '/lobbies/pgsoft',      'Sảnh PG SOFT - game đồng bộ tự động'],
  ['06-sanh-deno-casino',   '/lobbies/deno-casino', 'Sảnh Deno Casino - Baccarat / Dragon Tiger / Sicbo'],
  ['07-cong-cu-tien-ich',   '/tools',               'Bộ công cụ tiện ích'],
  ['08-dien-dan',           '/services/forum',      'Diễn đàn cộng đồng'],
  ['09-xem-phim',           '/services/media',      'Kho phim - tích hợp TMDB API'],
  ['10-tao-cv',             '/services/cv',         'Tạo CV online có avatar'],
  ['11-thiet-ke-anh',       '/services/design',     'Thiết kế ảnh bằng AI'],
  ['12-tao-website',        '/services/website-builder', 'Tạo website tự động A-Z'],
  ['13-kho-proxy',          '/services/proxy',      'Kho proxy - đăng ký / mua'],
  ['14-vps-vmware',         '/services/vps',        'VPS VMware qua XVPS API'],
  ['15-vps-storevps',       '/services/vps-storevps', 'VPS StoreVPS / Virtualizor'],
  ['16-mua-domain-hosting', '/services/infra',      'Mua domain / hosting / VPS'],
  ['17-pos-ke-toan',        '/services/pos',        'POS & phần mềm kế toán'],
  ['18-tra-cuu-ngan-hang',  '/services/bank-lookup','Tra cứu ngân hàng VietQR'],
  ['19-api-docs',           '/api/docs',            'Tài liệu API đối tác'],
  ['20-api-spec',           '/api/spec',            'OpenAPI specification'],
  ['21-dang-nhap',          '/login',               'Đăng nhập'],
  ['22-dang-ky',            '/register',            'Đăng ký (có mã giới thiệu)'],
  ['23-tin-tuc',            '/news',                'Tin tức & sự kiện'],
  ['24-vip-tiers',          '/packages',            'Gói chơi & VIP 60 cấp'],
  ['25-thu-vien',           '/market',              'Chợ tài nguyên'],
];

const ADMIN_PAGES = [
  ['26-admin-dashboard',    '/admin',                 'Quản trị - Dashboard'],
  ['27-admin-users',        '/admin/users',           'Quản trị - Người dùng'],
  ['28-admin-games',        '/admin/games',           'Quản trị - Quản lý game'],
  ['29-admin-deposits',     '/admin/deposits',        'Quản trị - Nạp tiền'],
  ['30-admin-withdrawals',  '/admin/withdrawals',     'Quản trị - Rút tiền'],
  ['31-admin-packages',     '/admin/packages',        'Quản trị - Gói chơi'],
  ['32-admin-banks',        '/admin/banks',           'Quản trị - Ngân hàng VietQR'],
  ['33-admin-virtual-bank', '/admin/virtual-bank',    'Quản trị - Bank ảo'],
  ['34-admin-ai-accounts',  '/admin/ai-accounts',     'Quản trị - Tài khoản AI (GenzShop)'],
  ['35-admin-vuavia',       '/admin/vuavia-accounts', 'Quản trị - Tài khoản & Nick (Vuavia)'],
  ['36-admin-otp',          '/admin/otp-rental',      'Quản trị - Thuê số OTP (ViOTP)'],
  ['37-admin-auto-bank',    '/admin/auto-bank',       'Quản trị - Auto Bank'],
  ['38-admin-automxh',      '/admin/automxh',         'Quản trị - Auto MXH'],
  ['39-admin-smm',          '/admin/smm',             'Quản trị - SMM'],
  ['40-admin-vps',          '/admin/vps',             'Quản trị - VPS VMware'],
  ['41-admin-vps-storevps', '/admin/vps-storevps',    'Quản trị - VPS StoreVPS'],
  ['42-admin-cso',          '/admin/cso',             'Quản trị - CSO / đại lý'],
  ['43-admin-tickets',      '/admin/tickets',         'Quản trị - Hỗ trợ / Ticket'],
  ['44-admin-news',         '/admin/news',            'Quản trị - Tin tức'],
  ['45-admin-ads',          '/admin/ads',             'Quản trị - Banner quảng cáo'],
  ['46-admin-child-sites',  '/admin/child-sites',     'Quản trị - Website con'],
  ['47-admin-settings',     '/admin/settings',        'Quản trị - Cấu hình hệ thống'],
  ['48-admin-security',     '/admin/security',        'Quản trị - Nhật ký bảo mật'],
  ['49-admin-service-prices','/admin/service-prices', 'Quản trị - Bảng giá dịch vụ'],
  ['50-admin-service-overview','/admin/service-overview','Quản trị - Tổng quan dịch vụ'],
  ['51-admin-phone-checks', '/admin/phone-checks',    'Quản trị - Nhật ký check SĐT'],
  ['52-admin-site-jobs',    '/admin/site-jobs',       'Quản trị - Dự án website'],
  ['53-admin-sms-campaigns','/admin/sms-campaigns',   'Quản trị - Chiến dịch SMS'],
  ['54-admin-campaigns',    '/admin/campaigns',       'Quản trị - Gửi email hàng loạt'],
  ['55-admin-giftcodes',    '/admin/giftcodes',       'Quản trị - Giftcode'],
  ['56-admin-market',       '/admin/market',          'Quản trị - Chợ tài nguyên'],
  ['57-admin-accounting',   '/services/accounting',   'Kế toán MISA-style (fullscreen)'],
];

// ---------------------------------------------------------------------------

function getFreePort() {
  return new Promise((resolve) => {
    const srv = net.createServer();
    srv.listen(0, '127.0.0.1', () => {
      const p = srv.address().port;
      srv.close(() => resolve(p));
    });
  });
}

function waitFor(fn, timeout = 30000, interval = 250) {
  return new Promise((resolve, reject) => {
    const started = Date.now();
    const tick = () => {
      Promise.resolve(fn()).then((ok) => {
        if (ok) return resolve(true);
        if (Date.now() - started > timeout) return reject(new Error('timeout'));
        setTimeout(tick, interval);
      }).catch(() => {
        if (Date.now() - started > timeout) return reject(new Error('timeout'));
        setTimeout(tick, interval);
      });
    };
    tick();
  });
}

function httpGet(url) {
  return new Promise((resolve, reject) => {
    const req = http.get(url, (res) => {
      let d = '';
      res.on('data', (c) => (d += c));
      res.on('end', () => resolve(d));
    });
    req.on('error', reject);
    req.setTimeout(5000, () => req.destroy(new Error('req timeout')));
  });
}

class CDP {
  constructor(ws) {
    this.ws = ws;
    this.id = 0;
    this.pending = new Map();
    ws.on('message', (raw) => {
      let msg;
      try { msg = JSON.parse(raw.toString()); } catch (e) { return; }
      if (msg.id && this.pending.has(msg.id)) {
        const { resolve, reject } = this.pending.get(msg.id);
        this.pending.delete(msg.id);
        msg.error ? reject(new Error(msg.error.message)) : resolve(msg.result);
      }
    });
  }
  send(method, params = {}, sessionId) {
    const id = ++this.id;
    const payload = { id, method, params };
    if (sessionId) payload.sessionId = sessionId;
    this.ws.send(JSON.stringify(payload));
    return new Promise((resolve, reject) => {
      this.pending.set(id, { resolve, reject });
      setTimeout(() => {
        if (this.pending.has(id)) {
          this.pending.delete(id);
          reject(new Error('CDP timeout: ' + method));
        }
      }, 45000);
    });
  }
}

async function main() {
  if (!fs.existsSync(OUT)) fs.mkdirSync(OUT, { recursive: true });

  const port = await getFreePort();
  console.log('Chrome CDP port:', port);

  const chrome = spawn(CHROME, [
    '--headless=new', '--disable-gpu', '--no-sandbox', '--hide-scrollbars',
    '--disable-dev-shm-usage', '--force-device-scale-factor=1',
    '--remote-debugging-port=' + port,
    '--remote-allow-origins=*',
    '--user-data-dir=' + PROFILE,
    '--window-size=1600,1000',
    'about:blank',
  ], { stdio: 'ignore' });

  let ws = null;
  try {
    // Chờ Chrome sẵn sàng
    let targets = null;
    await waitFor(async () => {
      const r = await httpGet('http://127.0.0.1:' + port + '/json/list');
      targets = JSON.parse(r);
      return targets.some((t) => t.type === 'page');
    }, 25000);

    const page = targets.find((t) => t.type === 'page');
    ws = new WebSocket(page.webSocketDebuggerUrl, { origin: 'http://localhost' });
    await new Promise((res, rej) => {
      ws.on('open', res);
      ws.on('error', rej);
      setTimeout(() => rej(new Error('ws open timeout')), 15000);
    });

    const c = new CDP(ws);
    await c.send('Page.enable');
    await c.send('Runtime.enable');
    await c.send('Network.enable');
    await c.send('Emulation.setDeviceMetricsOverride', {
      width: 1600, height: 1000, deviceScaleFactor: 1, mobile: false,
    });

    // --- Đăng nhập admin ---
    console.log('\n[DANG NHAP] admin / ' + ADMIN_PASS);
    await c.send('Page.navigate', { url: BASE + '/login' });
    await sleep(2500);
    const loginOk = await doLogin(c, ADMIN_USER, ADMIN_PASS);
    console.log(loginOk ? '  -> Dang nhap THANH CONG' : '  -> Dang nhap that bai (van chup)');
    await sleep(1500);

    const results = [];

    // --- Chụp trang khách ---
    console.log('\n--- TRANG KHACH ---');
    for (const [name, urlPath, desc] of GUEST_PAGES) {
      const r = await capture(c, name, urlPath);
      results.push(r);
    }

    // --- Chụp trang admin ---
    console.log('\n--- TRANG QUAN TRI ---');
    for (const [name, urlPath, desc] of ADMIN_PAGES) {
      const r = await capture(c, name, urlPath);
      results.push(r);
    }

    // --- Xuất manifest ---
    const pages = results.filter((r) => r.ok).map((r, i) => ({
      idx: i + 1, file: r.name + '.png', title: r.desc, url: r.urlPath,
    }));
    fs.writeFileSync(
      path.join(OUT, 'index.json'),
      JSON.stringify({
        generated: new Date().toISOString(),
        baseUrl: BASE,
        total: pages.length,
        pages,
      }, null, 2)
    );

    console.log('\n=== HOAN TAT: ' + pages.length + '/' + results.length + ' anh ===');
    const bad = results.filter((r) => !r.ok);
    if (bad.length) console.log('That bai: ' + bad.map((b) => b.name).join(', '));
  } catch (e) {
    console.error('LOI:', e.message, e.stack);
    process.exitCode = 1;
  } finally {
    try { if (ws) ws.close(); } catch (e) {}
    try { chrome.kill(); } catch (e) {}
    // dọn profile
    try { fs.rmSync(PROFILE, { recursive: true, force: true }); } catch (e) {}
  }
}

function sleep(ms) { return new Promise((r) => setTimeout(r, ms)); }

async function doLogin(c, user, pass) {
  try {
    // Bước 1: điền form (không submit)
    const fill = await c.send('Runtime.evaluate', {
      expression: `(() => {
        const setVal = (el, v) => {
          const proto = Object.getPrototypeOf(el);
          const desc = Object.getOwnPropertyDescriptor(proto, 'value');
          if (desc && desc.set) desc.set.call(el, v); else el.value = v;
          el.dispatchEvent(new Event('input', { bubbles: true }));
          el.dispatchEvent(new Event('change', { bubbles: true }));
        };
        const inputs = Array.from(document.querySelectorAll('input'));
        const userEl = inputs.find(i => /^(login|email|username|user)$/i.test(i.name||''))
          || inputs.find(i => i.type === 'text');
        const pwEl = inputs.find(i => i.type === 'password');
        if (!userEl || !pwEl) return { ok:false, msg:'khong tim thay form',
          inputs: inputs.map(i=>i.name+'|'+i.type+'|'+i.id) };
        setVal(userEl, ${JSON.stringify(user)});
        setVal(pwEl, ${JSON.stringify(pass)});
        return { ok:true };
      })()`,
      returnByValue: true,
    });
    const fv = fill.result && fill.result.value;
    if (!fv || !fv.ok) {
      console.log('   debug:', JSON.stringify(fv).slice(0, 300));
      return false;
    }

    // Bước 2: submit bằng fetch để không bị navigation làm hỏng CDP context
    const sub = await c.send('Runtime.evaluate', {
      expression: `(async () => {
        const sleep = (ms) => new Promise(r => setTimeout(r, ms));
        const form = document.querySelector('form[method=POST], form');
        const fd = new FormData(form);
        const action = form.getAttribute('action') || location.pathname;
        try {
          const res = await fetch(action, {
            method: 'POST', body: fd, redirect: 'follow',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin'
          });
          const txt = await res.text();
          return { ok: res.ok, status: res.status, url: res.url,
                   hasError: /sai|khong dung|invalid|error/i.test(txt.slice(0, 3000)) };
        } catch (e) { return { ok:false, msg: e.message }; }
      })()`,
      awaitPromise: true, returnByValue: true,
    });
    const sv = sub.result && sub.result.value;
    console.log('   submit:', JSON.stringify(sv).slice(0, 200));
    await sleep(800);

    // Bước 3: kiểm tra session bằng cách gọi /admin
    const chk = await c.send('Runtime.evaluate', {
      expression: `(async () => {
        const r = await fetch('/admin', { credentials: 'same-origin', redirect: 'follow' });
        const t = await r.text();
        return { url: r.url, ok: r.ok && !/\\/login/.test(r.url) && /Dashboard|Quản trị|dashboard/i.test(t) };
      })()`,
      awaitPromise: true, returnByValue: true,
    });
    const cv = chk.result && chk.result.value;
    console.log('   check:', JSON.stringify(cv).slice(0, 200));
    return !!(cv && cv.ok);
  } catch (e) {
    console.log('   login err:', e.message);
    return false;
  }
}

async function capture(c, name, urlPath, desc) {
  const url = BASE + urlPath;
  process.stdout.write('[shot] ' + name.padEnd(26) + urlPath.padEnd(28));
  try {
    await c.send('Page.navigate', { url });
    await sleep(3200);

    // Scroll để kích hoạt lazy-load + animation
    await c.send('Runtime.evaluate', {
      expression: `(async () => {
        const h = document.body ? document.body.scrollHeight : 0;
        for (let y = 0; y < h; y += 500) { window.scrollTo(0, y); await new Promise(r => setTimeout(r, 150)); }
        window.scrollTo(0, 0);
        await new Promise(r => setTimeout(r, 700));
        return { h: h, title: document.title, len: document.body ? document.body.innerText.length : 0 };
      })()`,
      awaitPromise: true, returnByValue: true,
    }, undefined).catch(() => {});
    await sleep(1400);

    const { data } = await c.send('Page.captureScreenshot', {
      format: 'png', captureBeyondViewport: false,
    });

    const file = path.join(OUT, name + '.png');
    fs.writeFileSync(file, Buffer.from(data, 'base64'));
    const kb = Math.round(fs.statSync(file).size / 1024);
    console.log('OK ' + kb + ' KB');
    return { name, urlPath, desc, ok: true, kb };
  } catch (e) {
    console.log('FAIL ' + e.message);
    return { name, urlPath, desc, ok: false };
  }
}

main();
