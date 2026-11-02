<?php
namespace App\Controllers;

use App\Core\Auth;
use App\Core\CSRF;
use App\Core\Database;
use App\Services\AIService;
use App\Services\BankLookupService;
use App\Services\CvService;
use App\Services\DesignService;
use App\Services\ForumService;
use App\Services\InfraService;
use App\Services\MediaService;
use App\Services\PosService;
use App\Services\ProxyService;
use App\Services\WebsiteBuilderService;

/**
 * Controller cho nhóm dịch vụ mở rộng.
 *
 * Mỗi module có 1 route rõ ràng, giao diện dùng chung layout chính
 * (trừ POS dùng layout riêng fullscreen).
 */
class ServicesController
{
    // ================= AI =================

    public function ai(): void
    {
        $models  = AIService::models();
        $agents  = AIService::agents();
        $recent  = Database::fetchAll(
            'SELECT a.*, m.label AS model_label FROM ai_agents a
             LEFT JOIN ai_models m ON m.id = a.model_id ORDER BY a.id DESC LIMIT 30'
        );

        view('services/ai', [
            'title'  => 'Trung tâm AI',
            'models' => $models,
            'agents' => $agents,
            'recent' => $recent,
        ]);
    }

    public function aiChat(): void
    {
        if (!is_ajax()) {
            redirect(url('/services/ai'));
        }

        $modelId = (int)($_POST['model_id'] ?? 0);
        $prompt  = trim((string)($_POST['prompt'] ?? ''));
        if ($prompt === '' || $modelId <= 0) {
            json_error('Thiếu model hoặc nội dung.');
        }

        $model = AIService::findModel($modelId);
        if (!$model) {
            json_error('Không tìm thấy model.');
        }

        $messages = [['role' => 'user', 'content' => $prompt]];
        $res = AIService::chat($model, $messages, ['temperature' => 0.7, 'max_tokens' => 2000]);

        if (!$res['ok']) {
            json_error($res['message']);
        }

        json_success([
            'text'  => $res['text'],
            'usage' => $res['usage'],
            'cost'  => AIService::estimateCost($model, $res['usage']),
        ]);
    }

    // ================= Diễn đàn =================

    public function forum(): void
    {
        $category = (string)($_GET['category'] ?? '');
        $page     = max(1, (int)($_GET['page'] ?? 1));
        $limit    = 20;
        $offset   = ($page - 1) * $limit;

        $topics = ForumService::topics($category !== '' ? $category : null, $limit, $offset);
        $total  = ForumService::countTopics($category !== '' ? $category : null);

        view('services/forum', [
            'title'      => 'Diễn đàn',
            'topics'     => $topics,
            'total'      => $total,
            'page'       => $page,
            'pages'      => (int)ceil($total / $limit),
            'category'   => $category,
            'categories' => ForumService::CATEGORIES,
        ]);
    }

    public function forumCreate(): void
    {
        CSRF::verifyOrFail();

        $res = ForumService::createTopic((int)Auth::id(), [
            'title'    => $_POST['title'] ?? '',
            'body'     => $_POST['body'] ?? '',
            'category' => $_POST['category'] ?? 'general',
        ]);

        flash($res['ok'] ? 'success' : 'error', $res['message']);
        redirect($res['ok'] ? url('/services/forum/topic/' . $res['slug']) : url('/services/forum'));
    }

    public function forumTopic(string $slug): void
    {
        $topic = ForumService::findBySlug($slug);
        if (!$topic) {
            abort(404, 'Không tìm thấy chủ đề');
        }

        Database::query('UPDATE forum_topics SET views = views + 1 WHERE id = ?', [(int)$topic['id']]);

        view('services/forum-topic', [
            'title'  => $topic['title'],
            'topic'  => $topic,
            'posts'  => ForumService::posts((int)$topic['id']),
            'categories' => ForumService::CATEGORIES,
        ]);
    }

    public function forumReply(): void
    {
        CSRF::verifyOrFail();

        $topicId = (int)($_POST['topic_id'] ?? 0);
        $body    = trim((string)($_POST['body'] ?? ''));

        $res = ForumService::reply($topicId, (int)Auth::id(), $body);
        flash($res['ok'] ? 'success' : 'error', $res['message']);

        $topic = ForumService::findTopic($topicId);
        redirect($topic ? url('/services/forum/topic/' . $topic['slug']) : url('/services/forum'));
    }

    // ================= CV =================

    public function cv(): void
    {
        $profiles = CvService::myProfiles((int)Auth::id());
        $editId   = (int)($_GET['edit'] ?? 0);
        $editing  = null;
        if ($editId > 0) {
            $editing = CvService::find($editId);
            if ($editing && (int)$editing['user_id'] !== (int)Auth::id()) {
                $editing = null;
            }
        }

        view('services/cv', [
            'title'     => 'Tạo CV',
            'profiles'  => $profiles,
            'editing'   => $editing,
            'templates' => CvService::templates(),
            'sections'  => CvService::sections(),
            'fonts'     => CvService::fonts(),
        ]);
    }

    public function cvSave(): void
    {
        CSRF::verifyOrFail();

        $res = CvService::save((int)Auth::id(), [
            'id'        => $_POST['id'] ?? 0,
            'title'     => $_POST['title'] ?? '',
            'full_name' => $_POST['full_name'] ?? '',
            'headline'  => $_POST['headline'] ?? '',
            'email'     => $_POST['email'] ?? '',
            'phone'     => $_POST['phone'] ?? '',
            'address'   => $_POST['address'] ?? '',
            'links'     => $_POST['links'] ?? '',
            'summary'   => $_POST['summary'] ?? '',
            'experience'=> $_POST['experience'] ?? '',
            'education' => $_POST['education'] ?? '',
            'skills'    => $_POST['skills'] ?? '',
            'languages' => $_POST['languages'] ?? '',
            'sections'  => $_POST['sections'] ?? '',
            'template'  => $_POST['template'] ?? 'modern',
            'accent'    => $_POST['accent'] ?? '#f5c542',
            'font_family' => $_POST['font_family'] ?? 'Inter',
            'avatar'    => self::saveCvAvatar((int)Auth::id()),
        ]);

        flash($res['ok'] ? 'success' : 'error', $res['message']);
        redirect(url('/services/cv'));
    }

    /**
     * Lưu ảnh đại diện CV vào /public/uploads/cv.
     */
    private static function saveCvAvatar(int $userId): ?string
    {
        if (empty($_FILES['avatar']) || !is_array($_FILES['avatar'])) {
            return null;
        }
        $file = $_FILES['avatar'];
        if ((int)($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return null;
        }
        if ((int)($file['error'] ?? 0) !== UPLOAD_ERR_OK) {
            flash('error', 'Không tải lên được ảnh đại diện.');
            return null;
        }
        if ((int)($file['size'] ?? 0) > 3 * 1024 * 1024) {
            flash('error', 'Ảnh đại diện không được vượt quá 3MB.');
            return null;
        }

        $ext = strtolower(pathinfo((string)($file['name'] ?? ''), PATHINFO_EXTENSION));
        if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif'], true)) {
            flash('error', 'Ảnh phải là JPG, PNG, WEBP hoặc GIF.');
            return null;
        }

        $mime = (string)(@mime_content_type((string)$file['tmp_name']) ?: '');
        if ($mime !== '' && !in_array($mime, ['image/jpeg', 'image/png', 'image/gif', 'image/webp'], true)) {
            flash('error', 'Tệp tải lên không phải hình ảnh hợp lệ.');
            return null;
        }

        $dir = BASE_PATH . '/public/uploads/cv';
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        $filename = 'cv_' . $userId . '_' . time() . '_' . bin2hex(random_bytes(3)) . '.' . $ext;
        if (!@move_uploaded_file((string)$file['tmp_name'], $dir . '/' . $filename)) {
            flash('error', 'Không lưu được ảnh đại diện.');
            return null;
        }

        return '/uploads/cv/' . $filename;
    }

    public function cvView(int $id): void
    {
        $cv = CvService::find($id);
        if (!$cv || ((int)$cv['user_id'] !== (int)Auth::id() && !Auth::isAdmin())) {
            abort(404, 'Không tìm thấy CV');
        }

        view('services/cv-view', [
            'title' => $cv['title'],
            'cv'    => $cv,
            'html'  => CvService::renderHtml($cv),
        ]);
    }

    // ================= Xem phim =================

    public function media(): void
    {
        $type  = (string)($_GET['type'] ?? '');
        $q     = trim((string)($_GET['q'] ?? ''));
        $genre = trim((string)($_GET['genre'] ?? ''));
        $page  = max(1, (int)($_GET['page'] ?? 1));
        $limit = 18;
        $offset = ($page - 1) * $limit;

        $items = MediaService::list($type !== '' ? $type : null, $q, $limit, $offset, $genre);
        $total = MediaService::count($type !== '' ? $type : null, $q, $genre);

        view('services/media', [
            'title'   => 'Xem phim',
            'items'   => $items,
            'total'   => $total,
            'page'    => $page,
            'pages'   => (int)ceil($total / $limit),
            'type'    => $type,
            'q'       => $q,
            'genre'   => $genre,
            'genres'  => MediaService::availableGenres(),
            'types'   => MediaService::TYPES,
        ]);
    }

    /**
     * Đồng bộ danh mục phim tự động từ nguồn phim chính thức.
     */
    public function mediaSync(): void
    {
        $res = MediaService::syncFromTmdb(2);
        flash($res['ok'] ? 'success' : 'error', $res['message']);
        redirect(url('/services/media'));
    }

    public function mediaWatch(string $slug): void
    {
        $item = MediaService::findBySlug($slug);
        if (!$item) {
            abort(404, 'Không tìm thấy phim');
        }
        MediaService::incrementViews((int)$item['id']);

        view('services/media-watch', [
            'title' => $item['title'],
            'item'  => $item,
            'embed' => MediaService::embedUrl((string)($item['video_url'] ?: $item['trailer_url'])),
        ]);
    }

    // ================= Thiết kế ảnh =================

    public function design(): void
    {
        $category = (string)($_GET['category'] ?? 'poster');

        view('services/design', [
            'title'      => 'Thiết kế ảnh',
            'category'   => $category,
            'categories' => DesignService::CATEGORIES,
            'schema'     => DesignService::schema($category),
            'variants'   => DesignService::variants(),
            'projects'   => DesignService::myProjects((int)Auth::id()),
        ]);
    }

    public function designCreate(): void
    {
        CSRF::verifyOrFail();

        $res = DesignService::createProject((int)Auth::id(), $_POST);
        flash($res['ok'] ? 'success' : 'error', $res['message']);

        redirect($res['ok'] ? url('/services/design/preview/' . $res['id']) : url('/services/design?category=' . urlencode($_POST['category'] ?? 'poster')));
    }

    public function designPreview(int $id): void
    {
        $p = DesignService::findProject($id);
        if (!$p || ((int)$p['user_id'] !== (int)Auth::id() && !Auth::isAdmin())) {
            abort(404, 'Không tìm thấy dự án');
        }

        view('services/design-preview', [
            'title' => $p['name'],
            'project' => $p,
            'svg'  => DesignService::renderSvg($p),
        ]);
    }

    // ================= Domain / Hosting =================

    public function infra(): void
    {
        $domain = trim((string)($_GET['domain'] ?? ''));
        $check  = null;
        if ($domain !== '') {
            $check = InfraService::checkDomain($domain);
        }

        view('services/infra', [
            'title'    => 'Domain & Hạ tầng',
            'plans'    => InfraService::plans(),
            'check'    => $check,
            'domain'   => $domain,
            'myDomains'=> InfraService::myDomains((int)Auth::id()),
            'tldPrice' => InfraService::TLD_PRICE,
        ]);
    }

    public function infraCheck(): void
    {
        CSRF::verifyOrFail();

        $domain = trim((string)($_POST['domain'] ?? ''));
        $check  = InfraService::checkDomain($domain);

        flash($check['status'] === 'available' ? 'success' : 'info', $check['note']);
        redirect(url('/services/infra?domain=' . urlencode($domain)));
    }

    public function infraOrder(): void
    {
        CSRF::verifyOrFail();

        $res = InfraService::orderDomain(
            (int)Auth::id(),
            (string)($_POST['domain'] ?? ''),
            (int)($_POST['years'] ?? 1)
        );

        flash($res['ok'] ? 'success' : 'error', $res['message']);
        redirect(url('/services/infra'));
    }

    // ================= Tra cứu ngân hàng =================

    public function bankLookup(): void
    {
        $accountNo = trim((string)($_GET['account_no'] ?? ''));
        $bankCode  = trim((string)($_GET['bank_code'] ?? ''));
        $result    = null;

        if ($accountNo !== '' && $bankCode !== '') {
            $result = BankLookupService::lookup($accountNo, $bankCode);
        }

        view('services/bank-lookup', [
            'title'     => 'Tra cứu ngân hàng',
            'banks'     => BankLookupService::banks(),
            'accountNo' => $accountNo,
            'bankCode'  => $bankCode,
            'result'    => $result,
        ]);
    }

    // ================= Proxy =================

    public function proxy(): void
    {
        $kind  = (string)($_GET['kind'] ?? '');
        $q     = trim((string)($_GET['q'] ?? ''));
        $page  = max(1, (int)($_GET['page'] ?? 1));
        $limit = 20;
        $offset = ($page - 1) * $limit;

        $items = ProxyService::list($kind !== '' ? $kind : null, $q, $limit, $offset);
        $total = ProxyService::count($kind !== '' ? $kind : null, $q);

        view('services/proxy', [
            'title'  => 'Kho proxy',
            'items'  => $items,
            'total'  => $total,
            'page'   => $page,
            'pages'  => (int)ceil($total / $limit),
            'kind'   => $kind,
            'q'      => $q,
            'kinds'  => ProxyService::KINDS,
        ]);
    }

    /** Danh sách đơn mua proxy của tôi */
    public function proxyOrders(): void
    {
        view('services/proxy-orders', [
            'title'  => 'Proxy đã mua',
            'orders' => ProxyService::myOrders((int)Auth::id()),
        ]);
    }

    public function proxyCreate(): void
    {
        CSRF::verifyOrFail();

        $res = ProxyService::create((int)Auth::id(), $_POST);
        flash($res['ok'] ? 'success' : 'error', $res['message']);

        redirect(url('/services/proxy'));
    }

    /**
     * Mua proxy — thanh toán bằng số dư ví.
     */
    public function proxyPurchase(): void
    {
        CSRF::verifyOrFail();

        $listingId = (int)($_POST['listing_id'] ?? 0);
        $quantity  = max(1, (int)($_POST['quantity'] ?? 1));
        $voucher   = trim((string)($_POST['voucher'] ?? ''));

        $res = ProxyService::purchase((int)Auth::id(), $listingId, $quantity, $voucher);
        flash($res['ok'] ? 'success' : 'error', $res['message']);

        redirect(url('/services/proxy/orders'));
    }

    // ================= Website builder =================

    public function websiteBuilder(): void
    {
        view('services/website-builder', [
            'title'      => 'Thiết kế website',
            'platforms'  => WebsiteBuilderService::PLATFORMS,
            'prices'     => WebsiteBuilderService::priceTable(),
            'projects'   => WebsiteBuilderService::myProjects((int)Auth::id()),
        ]);
    }

    public function websiteBuilderCreate(): void
    {
        CSRF::verifyOrFail();

        $res = WebsiteBuilderService::create((int)Auth::id(), $_POST);
        flash($res['ok'] ? 'success' : 'error', $res['message']);

        redirect($res['ok'] ? url('/services/website-builder/project/' . $res['id']) : url('/services/website-builder'));
    }

    public function websiteBuilderProject(int $id): void
    {
        $p = WebsiteBuilderService::find($id);
        if (!$p || ((int)$p['user_id'] !== (int)Auth::id() && !Auth::isAdmin())) {
            abort(404, 'Không tìm thấy dự án');
        }

        view('services/website-project', [
            'title'   => $p['name'],
            'project' => $p,
            'platforms' => WebsiteBuilderService::PLATFORMS,
        ]);
    }

    public function websiteBuilderPay(): void
    {
        CSRF::verifyOrFail();

        $res = WebsiteBuilderService::pay((int)Auth::id(), (int)($_POST['project_id'] ?? 0));
        flash($res['ok'] ? 'success' : 'error', $res['message']);

        redirect($_SERVER['HTTP_REFERER'] ?? url('/services/website-builder'));
    }

    // ================= API thanh toán (bảo trì) =================

    public function paymentApi(): void
    {
        view('services/payment-api', [
            'title'      => 'API thanh toán & thuê API ngân hàng',
            'maintenance' => true,
            'providers'  => [
                ['name' => 'VietQR', 'desc' => 'Tạo QR nạp tiền theo ngân hàng, đối soát tự động.', 'status' => 'Đang phát triển'],
                ['name' => 'Napas / CTT', 'desc' => 'Cổng thanh toán nội địa, hỗ trợ quét QR.', 'status' => 'Đang phát triển'],
                ['name' => 'MoMo / ZaloPay', 'desc' => 'Ví điện tử, webhook xác nhận giao dịch tức thì.', 'status' => 'Đang phát triển'],
                ['name' => 'Bank API cho đối tác', 'desc' => 'Tra cứu STK, tên chủ tài khoản qua REST API.', 'status' => 'Đang phát triển'],
            ],
            'docs'       => [
                ['method' => 'POST', 'path' => '/api/bank/lookup', 'desc' => 'Tra cứu chủ tài khoản theo số TK + mã ngân hàng.'],
                ['method' => 'POST', 'path' => '/api/payment/create', 'desc' => 'Tạo phiếu nạp tiền, trả về QR + nội dung chuyển khoản.'],
                ['method' => 'GET',  'path' => '/api/payment/{id}/status', 'desc' => 'Trạng thái giao dịch, webhook đẩy khi có tiền.'],
                ['method' => 'POST', 'path' => '/api/payment/callback', 'desc' => 'Webhook nhận thông báo đối soát tự động.'],
            ],
        ]);
    }

    // ================= POS (fullscreen) =================

    public function pos(): void
    {
        $category = (string)($_GET['category'] ?? 'food');

        view('services/pos', [
            'title'      => 'Phần mềm quản lý bán hàng',
            'category'   => $category,
            'categories' => PosService::categories(),
            'products'   => PosService::products($category),
            'customers'  => PosService::customers(),
            'orders'     => PosService::orders(20),
            'stats'      => PosService::stats(),
            'paymentMethods' => PosService::PAYMENT_METHODS,
        ], 'layouts/pos');
    }

    public function posOrder(): void
    {
        CSRF::verifyOrFail();

        $items = json_decode((string)($_POST['items'] ?? '[]'), true);
        $res = PosService::createOrder([
            'items'          => is_array($items) ? $items : [],
            'customer_id'    => $_POST['customer_id'] ?? 0,
            'customer_name'  => $_POST['customer_name'] ?? '',
            'customer_phone' => $_POST['customer_phone'] ?? '',
            'discount'       => $_POST['discount'] ?? 0,
            'payment_method' => $_POST['payment_method'] ?? 'cash',
            'note'           => $_POST['note'] ?? '',
        ]);

        if (is_ajax()) {
            json_response($res, $res['ok'] ? 200 : 400);
        }

        flash($res['ok'] ? 'success' : 'error', $res['message']);
        redirect(url('/services/pos?category=' . urlencode($_POST['category'] ?? 'food')));
    }
}
