<?php
/**
 * Tài liệu API — bố cục chuẩn, có nút "Sao chép toàn bộ API cho AI Agent"
 * và nút sao chép link từng endpoint.
 *
 * @var array $spec
 */
$spec = $spec ?? \App\Services\ApiDocService::spec();

/** Base URL dùng cho ví dụ cURL */
$apiBase = rtrim(str_replace('/api', '', url('/api')), '/');
?>

<!-- Dữ liệu đầy đủ cho AI Agent (ẩn, dùng để copy 1 click) -->
<script type="application/json" id="apiAgentPayload"><?= json_encode([
    'title'       => 'Casino Portal — API tích hợp game',
    'base_url'    => $apiBase,
    'auth_header' => 'X-API-Key',
    'note'        => 'Gọi API server-side. Gửi header X-API-Key với key dạng pk_live_... Quota và rate-limit tính theo từng key.',
    'endpoints'   => $spec['endpoints'],
    'errors'      => $spec['errors'],
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) ?></script>

<div class="container py-4">

    <div class="api-doc-layout">
        <!-- ================= SIDEBAR ================= -->
        <aside class="api-doc-side api-nav">
            <div class="api-side-group">
                <h6>Tổng quan</h6>
                <a class="api-side-link active" href="#api-overview">Bắt đầu nhanh</a>
                <a class="api-side-link" href="#api-security">Bảo mật</a>
                <a class="api-side-link" href="#api-endpoints">Danh sách endpoint</a>
                <a class="api-side-link" href="#api-errors">Mã lỗi</a>
            </div>

            <div class="api-side-group">
                <h6>Endpoint</h6>
                <?php foreach ($spec['endpoints'] as $i => $ep): ?>
                    <a class="api-side-link" href="#ep<?= (int)$i ?>">
                        <span class="api-method m-<?= e($ep['method']) ?>" style="display:inline-block;padding:.1rem .35rem;min-width:auto">
                            <?= e($ep['method']) ?>
                        </span>
                        <code style="font-size:.72rem"><?= e($ep['path']) ?></code>
                    </a>
                <?php endforeach; ?>
            </div>
        </aside>

        <!-- ================= NỘI DUNG ================= -->
        <div>
            <!-- Header + nút copy -->
            <div class="d-flex flex-wrap justify-content-between align-items-end gap-2 mb-4">
                <div>
                    <h1 class="h3 fw-bold mb-1"><i class="fas fa-book text-gold me-2"></i>Tài liệu API</h1>
                    <p class="text-muted-2 mb-0">
                        Tích hợp game từ hệ thống của bạn vào website riêng.
                        Mọi request đều được xác thực bằng API key, giới hạn quota và ký HMAC.
                    </p>
                </div>
                <div class="d-flex flex-wrap gap-2">
                    <button type="button" class="btn btn-gold" id="btnCopyAllApi">
                        <i class="fas fa-robot me-1"></i>Sao chép toàn bộ API cho AI Agent
                    </button>
                    <a href="<?= e(url('/api-keys')) ?>" class="btn btn-outline-gold">
                        <i class="fas fa-key me-1"></i>Lấy API key
                    </a>
                    <a href="<?= e(url('/api/docs')) ?>" class="btn btn-outline-light" target="_blank" rel="noopener">
                        <i class="fas fa-file-code me-1"></i>OpenAPI (JSON)
                    </a>
                </div>
            </div>

            <div class="api-copy-bar">
                <span class="form-hint mb-0"><i class="fas fa-link me-1"></i>Base URL:</span>
                <code class="font-numeric" style="font-size:.8rem"><?= e($apiBase) ?></code>
                <button type="button" class="btn btn-sm btn-outline-light copy-text"
                        data-copy="<?= e($apiBase) ?>">
                    <i class="fas fa-copy me-1"></i>Sao chép
                </button>
                <span class="form-hint ms-auto mb-0">
                    <i class="fas fa-shield-halved me-1"></i>Xác thực: <code>X-API-Key</code>
                </span>
            </div>

            <!-- ============ Bắt đầu nhanh ============ -->
            <section id="api-overview" class="api-endpoint">
                <div class="api-endpoint-head">
                    <i class="fas fa-rocket text-gold"></i>
                    <span class="api-endpoint-path">Bắt đầu trong 3 bước</span>
                </div>
                <div class="api-endpoint-body">
                    <div class="row g-4">
                        <div class="col-md-4">
                            <div class="fw-bold mb-2"><span class="badge bg-gold text-dark me-2">1</span>Mua gói API</div>
                            <p class="text-muted-2 small mb-0">
                                Vào <a href="<?= e(url('/api-keys')) ?>" class="text-gold">trang API của tôi</a>,
                                thuê một gói. Hệ thống cấp ngay API key kèm quota và hạn sử dụng.
                            </p>
                        </div>
                        <div class="col-md-4">
                            <div class="fw-bold mb-2"><span class="badge bg-gold text-dark me-2">2</span>Gửi header</div>
                            <p class="text-muted-2 small mb-0">
                                Mọi request đều phải có header
                                <code class="text-success">X-API-Key: pk_live_...</code>
                            </p>
                        </div>
                        <div class="col-md-4">
                            <div class="fw-bold mb-2"><span class="badge bg-gold text-dark me-2">3</span>Gọi endpoint</div>
                            <p class="text-muted-2 small mb-0">
                                Lấy danh sách game, khởi tạo phiên chơi và đồng bộ số dư qua
                                <code class="text-success">/api/launch</code> và <code class="text-success">/api/wallet/change</code>.
                            </p>
                        </div>
                    </div>

                    <hr class="my-4" style="border-color:var(--casino-line)">

                    <div class="mb-3">
                        <div class="d-flex align-items-center gap-2 mb-2">
                            <div class="fw-bold small">Test nhanh bằng cURL</div>
                            <button type="button" class="btn btn-sm btn-outline-light copy-text"
                                    data-copy-target="#curlGames">
                                <i class="fas fa-copy me-1"></i>Sao chép
                            </button>
                        </div>
                        <code class="api-code d-block" id="curlGames">curl "<?= e($apiBase) ?>/api/games?limit=5" \
  -H "X-API-Key: pk_live_xxxxxxxxxx_yyyyyyyyyyyyyyyyyyyyyyyyyyyyyyyyyyyyyyyyyyyyy"</code>
                    </div>
                    <div>
                        <div class="d-flex align-items-center gap-2 mb-2">
                            <div class="fw-bold small">Khởi tạo phiên chơi</div>
                            <button type="button" class="btn btn-sm btn-outline-light copy-text"
                                    data-copy-target="#curlLaunch">
                                <i class="fas fa-copy me-1"></i>Sao chép
                            </button>
                        </div>
                        <code class="api-code d-block" id="curlLaunch">curl -X POST "<?= e($apiBase) ?>/api/launch" \
  -H "X-API-Key: pk_live_xxx_yyy" \
  -H "Content-Type: application/json" \
  -d '{"slug":"mahjong-ways"}'</code>
                    </div>
                </div>
            </section>

            <!-- ============ Bảo mật ============ -->
            <section id="api-security" class="api-endpoint">
                <div class="api-endpoint-head">
                    <i class="fas fa-shield-halved text-gold"></i>
                    <span class="api-endpoint-path">Bảo mật &amp; best practices</span>
                </div>
                <div class="api-endpoint-body">
                    <div class="row g-4">
                        <div class="col-md-6">
                            <h6 class="fw-bold mb-2">Bắt buộc</h6>
                            <ul class="text-muted-2 small mb-0 ps-3">
                                <li class="mb-1">Chỉ gọi API từ <b>server-side</b> (PHP/Node/Python…). Không đặt key trong JavaScript của trình duyệt.</li>
                                <li class="mb-1">Luôn dùng header <code class="text-success">X-API-Key</code>, không truyền qua query string (dễ lộ trong log, referrer).</li>
                                <li class="mb-1">Lưu key ở biến môi trường hoặc secret manager, <b>không</b> commit vào Git.</li>
                                <li class="mb-1">Bật HTTPS bắt buộc — key là bearer token, truyền qua HTTP là lộ hoàn toàn.</li>
                                <li class="mb-1">Xử lý lỗi <code class="text-warning">401</code> (key sai/hết hạn) và <code class="text-warning">429</code> (quota/rate limit) với backoff.</li>
                            </ul>
                        </div>
                        <div class="col-md-6">
                            <h6 class="fw-bold mb-2">Chống lộ &amp; giám sát</h6>
                            <ul class="text-muted-2 small mb-0 ps-3">
                                <li class="mb-1">Key được lưu dạng <b>hash SHA-256</b> trong DB; bản giải mã chỉ dùng để hiển thị lại cho chủ sở hữu, mã hóa <b>AES-256-GCM</b>.</li>
                                <li class="mb-1">Mỗi key có <b>quota/ngày</b> và <b>rate limit/phút</b> riêng — nếu bị lộ, thiệt hại có giới hạn.</li>
                                <li class="mb-1">Toàn bộ request được ghi log (endpoint, IP, status, thời gian) để phát hiện lạm dụng.</li>
                                <li class="mb-1">Tạo key riêng cho từng môi trường (dev / staging / prod) và <b>xoá key không dùng</b> định kỳ.</li>
                                <li class="mb-1">Dùng cơ chế <b>HMAC-SHA256</b> để xác thực chữ ký webhook nếu bạn nhận callback từ hệ thống.</li>
                            </ul>
                        </div>
                    </div>

                    <hr class="my-4" style="border-color:var(--casino-line)">

                    <div class="alert alert-warning mb-0 d-flex gap-2 align-items-start">
                        <i class="fas fa-exclamation-triangle mt-1"></i>
                        <div class="small">
                            <b>Không bao giờ</b> ghi key vào log, hiển thị trên trang web, hoặc gửi qua chat/email.
                            Nếu nghi ngờ bị lộ — vào <a href="<?= e(url('/api-keys')) ?>" class="text-gold">API của tôi</a> và khóa key ngay lập tức.
                        </div>
                    </div>
                </div>
            </section>

            <!-- ============ Danh sách endpoint ============ -->
            <section id="api-endpoints" class="api-endpoint">
                <div class="api-endpoint-head">
                    <i class="fas fa-list text-gold"></i>
                    <span class="api-endpoint-path">Danh sách endpoint (<?= count($spec['endpoints']) ?>)</span>
                    <button type="button" class="btn btn-sm btn-outline-light ms-auto copy-text"
                            data-copy-target="#endpointTable">
                        <i class="fas fa-copy me-1"></i>Sao chép bảng
                    </button>
                </div>
                <div class="api-endpoint-body">
                    <div class="table-responsive">
                        <table class="api-table" id="endpointTable">
                            <thead>
                                <tr>
                                    <th style="width:80px">Method</th>
                                    <th style="width:220px">Path</th>
                                    <th>Mô tả</th>
                                    <th style="width:110px">Auth</th>
                                    <th style="width:60px"></th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($spec['endpoints'] as $i => $ep): ?>
                                <tr>
                                    <td>
                                        <span class="api-method m-<?= e($ep['method']) ?>"><?= e($ep['method']) ?></span>
                                    </td>
                                    <td>
                                        <a href="#ep<?= (int)$i ?>" class="text-decoration-none">
                                            <code><?= e($ep['path']) ?></code>
                                        </a>
                                    </td>
                                    <td class="small"><?= e($ep['description']) ?></td>
                                    <td><span class="api-method m-GET" style="min-width:auto">X-API-Key</span></td>
                                    <td class="text-end">
                                        <button type="button" class="btn btn-sm btn-outline-light copy-text"
                                                title="Sao chép link endpoint"
                                                data-copy="<?= e($apiBase . $ep['path']) ?>">
                                            <i class="fas fa-link"></i>
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </section>

            <!-- ============ Chi tiết từng endpoint ============ -->
            <?php foreach ($spec['endpoints'] as $i => $ep): ?>
                <section class="api-endpoint" id="ep<?= (int)$i ?>">
                    <div class="api-endpoint-head">
                        <span class="api-method m-<?= e($ep['method']) ?>"><?= e($ep['method']) ?></span>
                        <span class="api-endpoint-path"><?= e($ep['path']) ?></span>
                        <div class="ms-auto d-flex gap-2">
                            <button type="button" class="btn btn-sm btn-outline-light copy-text"
                                    title="Sao chép link endpoint"
                                    data-copy="<?= e($apiBase . $ep['path']) ?>">
                                <i class="fas fa-link me-1"></i>Link
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-light copy-text"
                                    title="Sao chép cURL mẫu"
                                    data-copy-target="#ep<?= (int)$i ?>Curl">
                                <i class="fas fa-copy me-1"></i>cURL
                            </button>
                        </div>
                    </div>
                    <div class="api-endpoint-body">
                        <p class="text-muted-2 small mb-3"><?= e($ep['description']) ?></p>

                        <?php
                        $curl = 'curl -X ' . $ep['method'] . ' "' . $apiBase . $ep['path'] . '"'
                            . "\n  -H \"X-API-Key: pk_live_xxx_yyy\"";
                        if (!empty($ep['body'])) {
                            $curl .= "\n  -H \"Content-Type: application/json\""
                                . "\n  -d '" . json_encode(
                                    array_map(static fn($m) => 'value', $ep['body']),
                                    JSON_UNESCAPED_UNICODE
                                ) . "'";
                        }
                        ?>
                        <code class="api-code d-block mb-3" id="ep<?= (int)$i ?>Curl"><?= e($curl) ?></code>

                        <?php if (!empty($ep['params'])): ?>
                            <div class="fw-bold small mb-1">Tham số query</div>
                            <table class="api-table mb-3">
                                <tbody>
                                <?php foreach ($ep['params'] as $k => $meta): ?>
                                    <tr>
                                        <td style="width:34%"><code><?= e($k) ?></code></td>
                                        <td>
                                            <?= e($meta['type'] ?? '') ?>
                                            <?php if (!empty($meta['required'])): ?><span class="api-req"> *bắt buộc</span><?php endif; ?>
                                            <?php if (isset($meta['default'])): ?><span class="text-muted-2">(mặc định: <?= e((string)$meta['default']) ?>)</span><?php endif; ?>
                                            <?php if (!empty($meta['desc'])): ?><br><?= e($meta['desc']) ?><?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php endif; ?>

                        <?php if (!empty($ep['body'])): ?>
                            <div class="fw-bold small mb-1">Body (JSON)</div>
                            <table class="api-table mb-3">
                                <tbody>
                                <?php foreach ($ep['body'] as $k => $meta): ?>
                                    <tr>
                                        <td style="width:34%"><code><?= e($k) ?></code></td>
                                        <td>
                                            <?= e($meta['type'] ?? '') ?>
                                            <?php if (!empty($meta['required'])): ?><span class="api-req"> *bắt buộc</span><?php endif; ?>
                                            <?php if (!empty($meta['desc'])): ?><br><?= e($meta['desc']) ?><?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php endif; ?>

                        <?php if (!empty($ep['response'])): ?>
                            <div class="d-flex align-items-center gap-2 mb-1">
                                <div class="fw-bold small">Response</div>
                                <button type="button" class="btn btn-sm btn-outline-light copy-text"
                                        data-copy-target="#ep<?= (int)$i ?>Res">
                                    <i class="fas fa-copy"></i>
                                </button>
                            </div>
                            <code class="api-code d-block" id="ep<?= (int)$i ?>Res"><?= e(json_encode($ep['response'], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)) ?></code>
                        <?php endif; ?>
                    </div>
                </section>
            <?php endforeach; ?>

            <!-- ============ Mã lỗi ============ -->
            <section id="api-errors" class="api-endpoint">
                <div class="api-endpoint-head">
                    <i class="fas fa-circle-exclamation text-gold"></i>
                    <span class="api-endpoint-path">Mã lỗi</span>
                </div>
                <div class="api-endpoint-body">
                    <div class="table-responsive">
                        <table class="api-table">
                            <tbody>
                            <?php foreach ($spec['errors'] as $code => $desc): ?>
                                <tr>
                                    <td style="width:180px"><code><?= e($code) ?></code></td>
                                    <td><?= e($desc) ?></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </section>
        </div>
    </div>
</div>

<script>
(function () {
    // --- Sao chép text ---
    function copyText(text, btn) {
        var done = function () {
            var old = btn.innerHTML;
            btn.innerHTML = '<i class="fas fa-check me-1"></i>Đã sao chép';
            setTimeout(function () { btn.innerHTML = old; }, 1800);
        };
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(text).then(done, function () { fallback(text, done); });
        } else {
            fallback(text, done);
        }
    }

    function fallback(text, done) {
        var ta = document.createElement('textarea');
        ta.value = text;
        ta.style.position = 'fixed';
        ta.style.opacity = '0';
        document.body.appendChild(ta);
        ta.select();
        try { document.execCommand('copy'); done(); } catch (e) { /* ignore */ }
        document.body.removeChild(ta);
    }

    // --- Nút sao chép đơn lẻ ---
    document.querySelectorAll('.copy-text').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var text = btn.getAttribute('data-copy');
            if (!text) {
                var t = btn.getAttribute('data-copy-target');
                var el = t ? document.querySelector(t) : null;
                text = el ? el.textContent : '';
            }
            if (text) copyText(text.trim(), btn);
        });
    });

    // --- Nút "Sao chép toàn bộ API cho AI Agent" ---
    var btnAll = document.getElementById('btnCopyAllApi');
    if (btnAll) {
        btnAll.addEventListener('click', function () {
            var src = document.getElementById('apiAgentPayload');
            if (!src) return;
            var payload = src.textContent;
            // Bọc thêm hướng dẫn ngắn cho AI Agent
            var text = '# Tài liệu API — ' + '\n' + payload;
            copyText(text, btnAll);
        });
    }

    // --- Highlight endpoint khi bấm link trong sidebar ---
    var links = document.querySelectorAll('.api-side-link[href^="#"]');
    links.forEach(function (a) {
        a.addEventListener('click', function () {
            links.forEach(function (x) { x.classList.remove('active'); });
            a.classList.add('active');
        });
    });
})();
</script>
