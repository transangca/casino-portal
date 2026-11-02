/* =====================================================================
   Casino Portal — JS chung (Bootstrap 5 + SweetAlert2)
   ===================================================================== */
(function () {
    'use strict';

    const APP = window.APP || {};
    const $  = (sel, root) => (root || document).querySelector(sel);
    const $$ = (sel, root) => Array.from((root || document).querySelectorAll(sel));

    /* ---------------- AJAX helper ---------------- */
    function post(url, data) {
        const body = new URLSearchParams();
        Object.keys(data || {}).forEach(function (k) {
            body.append(k, data[k]);
        });
        return fetch(APP.baseUrl + url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': APP.csrfToken
            },
            body: body.toString()
        }).then(function (r) { return r.json(); });
    }
    window.post = post;

    /* ---------------- SweetAlert2 wrapper ---------------- */
    const Swal = window.Swal;

    function toastOk(text) {
        if (Swal) {
            Swal.fire({
                icon: 'success',
                title: text,
                timer: 2600,
                showConfirmButton: false,
                customClass: { popup: 'swal-dark' }
            });
        }
    }
    function toastErr(text) {
        if (Swal) {
            Swal.fire({
                icon: 'error',
                title: 'Lỗi',
                text: text,
                timer: 3600,
                showConfirmButton: false,
                customClass: { popup: 'swal-dark' }
            });
        } else {
            alert(text);
        }
    }
    window.toastOk = toastOk;
    window.toastErr = toastErr;

    async function confirmBox(title, text, confirmLabel) {
        if (!Swal) {
            return window.confirm(text || title);
        }
        const res = await Swal.fire({
            title: title,
            text: text || '',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: confirmLabel || 'Đồng ý',
            cancelButtonText: 'Hủy',
            reverseButtons: true,
            customClass: { popup: 'swal-dark', confirmButton: 'btn btn-gold', cancelButton: 'btn btn-outline-light' }
        });
        return res.isConfirmed;
    }
    window.confirmBox = confirmBox;

    /* ---------------- Skeleton loading ---------------- */
    const Skeleton = {
        /** Hiện skeleton trong container, gọi fn trả về promise */
        show: function (container, template) {
            if (!container) return;
            container.innerHTML = template;
            container.setAttribute('aria-busy', 'true');
        },
        hide: function (container) {
            if (!container) return;
            container.removeAttribute('aria-busy');
        },
        /** Thay nội dung container bằng skeleton rồi render sau delay */
        render: function (container, skeletonHtml, finalHtml, delay) {
            if (!container) return;
            Skeleton.show(container, skeletonHtml);
            return new Promise(function (resolve) {
                setTimeout(function () {
                    container.innerHTML = finalHtml;
                    Skeleton.hide(container);
                    resolve();
                }, delay || 420);
            });
        },
        /** Skeleton dạng lưới game */
        grid: function (count) {
            let html = '<div class="game-grid">';
            for (let i = 0; i < (count || 12); i++) {
                html += '<div class="game-card">' +
                    '<div class="skeleton skel-thumb"></div>' +
                    '<div class="p-2"><div class="skeleton skel-text w-75"></div>' +
                    '<div class="skeleton skel-text w-50"></div></div>' +
                    '</div>';
            }
            return html + '</div>';
        },
        /** Skeleton dạng bảng */
        table: function (rows, cols) {
            let html = '<div class="casino-card p-3">';
            for (let r = 0; r < (rows || 5); r++) {
                html += '<div class="d-flex gap-3 mb-3">';
                for (let c = 0; c < (cols || 4); c++) {
                    html += '<div class="skeleton skel-text flex-fill"></div>';
                }
                html += '</div>';
            }
            return html + '</div>';
        },
        /** Skeleton dạng card */
        cards: function (count) {
            let html = '<div class="row g-3">';
            for (let i = 0; i < (count || 6); i++) {
                html += '<div class="col-md-4"><div class="casino-card p-3">' +
                    '<div class="skeleton skel-text"></div>' +
                    '<div class="skeleton skel-text w-75"></div>' +
                    '<div class="skeleton skel-text w-50"></div>' +
                    '</div></div>';
            }
            return html + '</div>';
        }
    };
    window.Skeleton = Skeleton;

    /* ---------------- Tự ẩn alert ---------------- */
    document.addEventListener('DOMContentLoaded', function () {
        setTimeout(function () {
            $$('.alert-dismissible').forEach(function (el) {
                if (window.bootstrap && bootstrap.Alert) {
                    const inst = bootstrap.Alert.getOrInstance(el);
                    if (inst) inst.close(); else el.remove();
                } else {
                    el.remove();
                }
            });
        }, 6000);
    });

    /* ---------------- Copy to clipboard (theo data-copy) ---------------- */
    document.addEventListener('click', function (e) {
        const btn = e.target.closest('[data-copy]');
        if (!btn) return;
        const text = btn.getAttribute('data-copy');
        const done = function () {
            const old = btn.innerHTML;
            btn.innerHTML = '<i class="fas fa-check"></i> Đã sao chép';
            toastOk('Đã sao chép vào bộ nhớ tạm');
            setTimeout(function () { btn.innerHTML = old; }, 1600);
        };
        if (navigator.clipboard && window.isSecureContext) {
            navigator.clipboard.writeText(text).then(done).catch(fallbackCopy);
        } else {
            fallbackCopy();
        }
        function fallbackCopy() {
            const ta = document.createElement('textarea');
            ta.value = text;
            ta.style.position = 'fixed';
            ta.style.opacity = '0';
            document.body.appendChild(ta);
            ta.select();
            try { document.execCommand('copy'); done(); } catch (err) { toastErr('Không copy được'); }
            document.body.removeChild(ta);
        }
    });

    /* ---------------- Copy to clipboard (theo data-copy-target → id) ---------------- */
    document.addEventListener('click', function (e) {
        const btn = e.target.closest('[data-copy-target]');
        if (!btn) return;
        const src = document.getElementById(btn.getAttribute('data-copy-target'));
        if (!src) return;
        const text = (src.textContent || '').trim();
        const done = function () {
            const old = btn.innerHTML;
            btn.innerHTML = '<i class="fas fa-check"></i>';
            if (typeof toastOk === 'function') toastOk('Đã sao chép');
            setTimeout(function () { btn.innerHTML = old; }, 1600);
        };
        if (navigator.clipboard && window.isSecureContext) {
            navigator.clipboard.writeText(text).then(done).catch(fallbackCopy);
        } else {
            fallbackCopy();
        }
        function fallbackCopy() {
            const ta = document.createElement('textarea');
            ta.value = text;
            ta.style.position = 'fixed';
            ta.style.opacity = '0';
            document.body.appendChild(ta);
            ta.select();
            try { document.execCommand('copy'); done(); } catch (err) { /* ignore */ }
            document.body.removeChild(ta);
        }
    });

    /* ---------------- Confirm trước khi submit ---------------- */
    document.addEventListener('submit', function (e) {
        const form = e.target;
        const msg = form.getAttribute('data-confirm');
        if (!msg) return;
        e.preventDefault();
        confirmBox('Xác nhận', msg, 'Đồng ý').then(function (ok) {
            if (ok) {
                form.removeAttribute('data-confirm');
                form.submit();
            }
        });
    });

    /* ---------------- Filter chips ---------------- */
    document.addEventListener('click', function (e) {
        const chip = e.target.closest('.filter-chip');
        if (!chip) return;
        const url = new URL(chip.href, window.location.origin);
        const key = chip.getAttribute('data-filter-key');
        if (key) {
            const keepArr = (chip.getAttribute('data-filter-keep') || '').split(',').filter(Boolean);
            const current = new URLSearchParams(window.location.search);
            const next = new URLSearchParams();
            keepArr.forEach(function (k) {
                if (current.has(k)) next.set(k, current.get(k));
            });
            next.set(key, chip.getAttribute('data-filter-value') || '');
            next.forEach(function (v, k) {
                if (v) url.searchParams.set(k, v); else url.searchParams.delete(k);
            });
            url.search = next.toString();
        }
        window.location.href = url.toString();
    });

    /* ---------------- Nạp tiền: tính tiền + tạo QR ---------------- */
    const depositInit = function () {
        const amountInput = document.getElementById('amount');
        const bankSelect  = document.getElementById('bank');
        const qrBox       = document.getElementById('qrBox');
        const qrImg       = document.getElementById('qrImg');
        const qrInfo      = document.getElementById('qrInfo');
        const btnCreate   = document.getElementById('btnCreateQr');
        const bonusText   = document.getElementById('bonusText');
        const summaryBox  = document.getElementById('summaryBox');

        // Trang nạp tiền không bắt buộc có #depositForm — chỉ cần #amount
        if (!amountInput || !btnCreate) return;

        const quickBtns = $$('[data-amount]');

        function fmt(n) {
            return Math.round(Number(n) || 0).toLocaleString('vi-VN');
        }

        function updateSummary() {
            const amount = Number(amountInput.value) || 0;
            const rate = Number(APP.bonusRate) || 0;
            const bonus = Math.round(amount * rate);
            if (bonusText) {
                bonusText.textContent = amount > 0
                    ? fmt(amount) + ' đ + thưởng ' + fmt(bonus) + ' đ'
                    : '—';
            }
            if (summaryBox) {
                summaryBox.classList.toggle('d-none', amount <= 0);
            }
            if (btnCreate) {
                btnCreate.disabled = amount <= 0 || !bankSelect || !bankSelect.value;
            }
            // Đánh dấu preset đang chọn
            quickBtns.forEach(function (b) {
                b.classList.toggle('active', Number(b.getAttribute('data-amount')) === amount);
            });
        }

        quickBtns.forEach(function (b) {
            b.addEventListener('click', function () {
                amountInput.value = b.getAttribute('data-amount');
                updateSummary();
            });
        });

        amountInput.addEventListener('input', updateSummary);
        if (bankSelect) bankSelect.addEventListener('change', updateSummary);
        updateSummary();

        if (!btnCreate) return;

        btnCreate.addEventListener('click', function () {
            const amount = Number(amountInput.value) || 0;
            const bank   = bankSelect ? bankSelect.value : '';

            if (amount <= 0) { toastErr('Vui lòng nhập số tiền muốn nạp.'); return; }
            if (!bank) { toastErr('Vui lòng chọn ngân hàng nhận tiền.'); return; }

            btnCreate.disabled = true;
            const oldHtml = btnCreate.innerHTML;
            btnCreate.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Đang tạo mã QR...';

            qrBox.classList.add('d-none');

            post('wallet/deposit/qr', { amount: amount, bank: bank })
                .then(function (res) {
                    if (!res || !res.success) {
                        toastErr((res && res.message) || 'Không tạo được mã QR. Vui lòng thử lại.');
                        return;
                    }
                    qrImg.src = res.data.qr_image;
                    qrInfo.innerHTML =
                        '<div class="mt-2 small text-muted-2">Số tiền: <b class="text-gold">' + fmt(res.data.amount) + ' đ</b></div>' +
                        '<div class="small text-muted-2">Nội dung: <b class="text-light">' + (res.data.code || '') + '</b></div>' +
                        '<div class="small text-muted-2">Ngân hàng: <b class="text-light">' + (res.data.bank_name || '') + '</b></div>' +
                        '<div class="small text-muted-2">Số TK: <b class="text-light">' + (res.data.account_no || '') + '</b></div>';
                    qrBox.classList.remove('d-none');
                    qrBox.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    toastOk('Đã tạo mã QR. Quét mã để chuyển khoản.');
                })
                .catch(function () {
                    toastErr('Lỗi mạng khi tạo mã QR.');
                })
                .finally(function () {
                    btnCreate.disabled = false;
                    btnCreate.innerHTML = oldHtml;
                });
        });
    };
    document.addEventListener('DOMContentLoaded', depositInit);

    /* ---------------- Game list: skeleton khi đổi filter ---------------- */
    document.addEventListener('DOMContentLoaded', function () {
        const grid = document.getElementById('gameGrid');
        if (!grid) return;
        // Hiện skeleton ngay khi trang vừa tải (nếu chưa có game)
        if (!grid.children.length) {
            grid.innerHTML = Skeleton.grid(12);
        }
    });

    /* ---------------- Bảo vệ: chống soi mã & chống dò DevTools ---------------- */
    /**
     * Cảnh báo thân thiện khi người dùng mở View Source / kiểm tra phần tử.
     * Không chặn tuyệt đối (trình duyệt luôn có thể lấy HTML) — mục tiêu là
     * ngăn việc sao chép dễ dàng và ghi cảnh báo vào nhật ký.
     */
    (function protectSource() {
        var enabled = window.APP && window.APP.antiDebug;
        if (!enabled) return;

        var shown = false;
        function warnSource() {
            if (shown) return;
            shown = true;
            if (window.Swal) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Nội dung này được bảo vệ',
                    html: 'Mã nguồn của trang là tài sản sở hữu trí tuệ.<br>' +
                          'Vui lòng liên hệ <b>kinh doanh</b> nếu bạn cần hợp tác API chính thức.',
                    confirmButtonText: 'Đã hiểu',
                    customClass: { popup: 'swal-dark' }
                });
            }
        }

        // Ctrl+U (View Source) / Ctrl+S (Save) / F12 / Ctrl+Shift+I / Ctrl+Shift+J
        document.addEventListener('keydown', function (e) {
            var k = (e.key || '').toLowerCase();
            if ((e.ctrlKey || e.metaKey) && (k === 'u' || k === 's')) {
                warnSource();
                e.preventDefault();
                return;
            }
            if (k === 'f12' || ((e.ctrlKey || e.metaKey) && e.shiftKey && (k === 'i' || k === 'j' || k === 'c'))) {
                warnSource();
            }
        }, true);

        // Chặn chuột phải để giảm khả năng "View Page Source" từ menu ngữ cảnh
        document.addEventListener('contextmenu', function (e) {
            if (e.target && e.target.closest && e.target.closest('input, textarea, [contenteditable="true"]')) {
                return;
            }
            e.preventDefault();
            warnSource();
        });

        // Phát hiện mở DevTools qua kích thước cửa sổ
        var threshold = 160;
        function checkDevTools() {
            var w = window.outerWidth - window.innerWidth;
            var h = window.outerHeight - window.innerHeight;
            if ((w > threshold || h > threshold) && !shown) {
                warnSource();
            }
        }
        setInterval(checkDevTools, 1500);

        // Vô hiệu hoá chức năng in + chặn kéo ảnh chụp màn hình
        window.addEventListener('beforeprint', function () {
            document.body.classList.add('printing-blocked');
        });
    })();

    /* ---------------- Auto-submit filter select ---------------- */
    document.addEventListener('change', function (e) {
        const sel = e.target.closest('[data-auto-submit]');
        if (sel && sel.form) sel.form.submit();
    });

    /* ---------------- Chặn in ấn (bảo vệ nội dung) ---------------- */
    window.addEventListener('afterprint', function () {
        document.body.classList.remove('printing-blocked');
    });

    /* ---------------- Navbar active state ---------------- */
    document.addEventListener('DOMContentLoaded', function () {
        const path = window.location.pathname;
        $$('.main-navbar .nav-link').forEach(function (link) {
            const href = link.getAttribute('href') || '';
            if (href !== '/' && path.startsWith(href)) {
                link.classList.add('active');
            }
        });
    });
})();
