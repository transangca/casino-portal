<?php
namespace App\Services\Provider;

/**
 * Interface chung cho mọi sảnh game.
 *
 * Mỗi sảnh có 2 chế độ phát game:
 *  - DEMO  : mở game trực tiếp từ CDN của nhà phát hành (không cần ví).
 *  - LIVE  : nhà phát hành gọi Operator API của ta để đồng bộ ví 2 chiều.
 *
 * Chuẩn PGSoft (HTML Scheme):
 *   {PublicDomain}/{gameId}/index.html?ot={operator_token}&ops={session}&btt={mode}&l={lang}
 *
 * Chuẩn KingMidas:
 *   {PublicDomain}/{gameId}/index.html?ot={operator_token}&l={lang}&btt={mode}
 */
interface ProviderInterface
{
    /** Mã sảnh */
    public function code(): string;

    /** Tên hiển thị */
    public function name(): string;

    /** Host game (không có dấu / cuối) */
    public function gameHost(): string;

    /**
     * Lấy danh sách game từ nhà cung cấp.
     * @return array<int, array> mỗi phần tử: game_id, name, icon, embed_url, demo_url, ...
     */
    public function fetchGames(): array;

    /**
     * Tạo URL nhúng để chơi game cho 1 user cụ thể.
     * Trả về ['url' => ..., 'token' => ...] hoặc null nếu không hỗ trợ.
     */
    public function buildLaunchUrl(array $game, array $user, array $context = []): ?array;

    /**
     * Đồng bộ số dư xuống nhà cung cấp (nếu provider hỗ trợ).
     */
    public function pushBalance(int $userId, float $amount): bool;

    /**
     * Lấy số dư hiện tại từ nhà cung cấp (nếu hỗ trợ).
     */
    public function fetchBalance(int $userId): ?float;

    /**
     * Chuẩn bet type của sảnh (1 = credit, 2 = demo/free).
     * Trả về null nếu sảnh không dùng tham số này.
     */
    public function defaultBetType(): ?int;
}
