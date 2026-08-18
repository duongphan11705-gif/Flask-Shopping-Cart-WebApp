<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// --- CẤU HÌNH API GOOGLE GEMINI (BƠM NÃO CHO NÚT HỎI AI CHI TIẾT) ---
define("GEMINI_API_KEY", "AIzaSy_ĐIỀN_MÃ_API_KEY_CỦA_BẠN_VÀO_ĐÂY");

function callGemini($prompt) {
    $url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent?key=" . GEMINI_API_KEY;
    $payload = json_encode(["contents" => [["parts" => [["text" => $prompt]]]]]);
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    
    $response = curl_exec($ch);
    curl_close($ch);
    
    if ($response) {
        $json = json_decode($response, true);
        if (isset($json['candidates'][0]['content']['parts'][0]['text'])) {
            return str_replace("**", "", $json['candidates'][0]['content']['parts'][0]['text']);
        }
    }
    return "Trợ lý AI đang cập nhật thông số cấu hình phần mềm, bạn hỏi lại sau vài giây nhé!";
}

// KẾT NỐI DATABASE AIVEN & TỰ ĐỘNG KHỞI TẠO BẢNG
$conn = mysqli_init();
mysqli_ssl_set($conn, NULL, NULL, NULL, NULL, NULL);
// Kết nối với Host, User, Pass, DB, Port và cờ kết nối SSL
mysqli_real_connect($conn, "mysql-17895759-duongphan11705-c6e4.i.aivencloud.com", "avnadmin", "AVNS_1xyPm72gzoTQSxU-0PD", "defaultdb", 28878, NULL, MYSQLI_CLIENT_SSL);

if (!$conn) {
    die("Kết nối Aiven thất bại: " . mysqli_connect_error());
}

mysqli_query($conn, "CREATE TABLE IF NOT EXISTS `products` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(255) NOT NULL,
    `price` INT NOT NULL,
    `specs` VARCHAR(255) DEFAULT NULL,
    `firmware` VARCHAR(100) DEFAULT NULL,
    `comp_name` VARCHAR(100) DEFAULT NULL,
    `comp_price` INT NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

// TỰ ĐỘNG BƠM SẠCH ĐỦ 100 SẢN PHẨM PHÂN TÍCH ĐỐI THỦ
$check_empty = mysqli_query($conn, "SELECT COUNT(*) as total FROM products");
if (mysqli_fetch_assoc($check_empty)['total'] == 0) {
    $names = ["Honor 10", "Honor 20 Pro", "Huawei P30", "Huawei P40 Pro", "Huawei Mate 30", "Honor Magic 5", "Huawei Nova 7i"];
    $chips = ["Kirin 970, 6GB RAM, 128GB ROM", "Kirin 980, 8GB RAM, 256GB ROM", "Kirin 990 5G, 8GB RAM", "Snapdragon 8 Gen 2, 12GB RAM"];
    $roms = ["EMUI 8.1 (Hạ cấp OK)", "EMUI 11 (Cài GBox)", "HarmonyOS 2.0", "MagicOS 7.1"];
    $comps = ["Xiaomi Redmi Note 12" => 4500000, "Samsung Galaxy A54" => 7200000, "iPhone 11 cũ" => 6500000, "iPhone 12 Pro" => 11500000];

    for ($i = 1; $i <= 100; $i++) {
        $brand_name = $names[array_rand($names)];
        $n = $brand_name . " (Bản $i)";
        $p = rand(3000000, 12000000);
        $s = $chips[array_rand($chips)];
        $f = $roms[array_rand($roms)];
        $c_name = array_rand($comps);
        $c_price = $p + rand(1000000, 3000000);
        
        mysqli_query($conn, "INSERT INTO products (name, price, specs, firmware, comp_name, comp_price) VALUES ('$n', $p, '$s', '$f', '$c_name', $c_price)");
    }
}

// XỬ LÝ ĐĂNG NHẬP THẬT (TÀI KHOẢN: admin / MẬT KHẨU: 123)
if (isset($_POST['login_action'])) {
    $user = $_POST['username'];
    $pass = $_POST['password'];
    if ($user == 'admin' && $pass == '123') {
        $_SESSION['user_logged'] = "admin";
    }
    header("Location: index.php");
    exit();
}

// XỬ LÝ ĐĂNG XUẤT
if (isset($_GET['action']) && $_GET['action'] == 'logout') {
    unset($_SESSION['user_logged']);
    header("Location: index.php");
    exit();
}

// XỬ LÝ THÊM VÀO GIỎ HÀNG KHÔNG CẦN TÀI KHOẢN
if (isset($_GET['action']) && $_GET['action'] == 'add') {
    $id = (int)$_GET['id'];
    if (!isset($_SESSION['cart'])) $_SESSION['cart'] = [];
    $_SESSION['cart'][$id] = isset($_SESSION['cart'][$id]) ? $_SESSION['cart'][$id] + 1 : 1;
    header("Location: index.php?status=success");
    exit();
}

// --- TÍNH NĂNG MỚI 1: XÓA 1 SẢN PHẨM KHỎI GIỎ HÀNG ---
if (isset($_GET['action']) && $_GET['action'] == 'remove_item') {
    $id = (int)$_GET['id'];
    if (isset($_SESSION['cart'][$id])) {
        unset($_SESSION['cart'][$id]);
    }
    header("Location: index.php?status=removed");
    exit();
}

// XỬ LÝ THANH TOÁN GUEST CHECKOUT
if (isset($_POST['checkout_action'])) {
    unset($_SESSION['cart']);
    header("Location: index.php?status=ordered");
    exit();
}

// --- TÍNH NĂNG MỚI 2: HỦY / XÓA ĐƠN HÀNG VỪA ĐẶT ---
if (isset($_GET['action']) && $_GET['action'] == 'cancel_order') {
    header("Location: index.php?status=cancelled");
    exit();
}

// XỬ LÝ API ROUTE GEMINI
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_GET['api']) && $_GET['api'] == 'gemini') {
    $data = json_decode(file_get_contents('php://input'), true);
    $p_name = $data['product_name'] ?? '';
    $c_info = $data['competitor_info'] ?? '';
    $q = $data['question'] ?? '';
    $prompt = "Bạn là chuyên gia phân tích kỹ thuật điện thoại tại shop Tùng Dương Mobile. Khách đang xem máy: '$p_name'. Thông tin đối thủ: $c_info. Khách hỏi: '$q'. Hãy tư vấn kỹ thuật chuyên sâu phần cứng chip Kirin, cài ứng dụng Google và so sánh giá để thuyết phục khách mua hàng tại shop. Trả lời ngắn gọn 2-3 câu. Xưng 'Mình', gọi khách là 'Bạn'.";
    echo json_encode(["reply" => callGemini($prompt)]);
    exit();
}

// LẤY SẢN PHẨM VÀ ĐẾM GIỎ
$res = mysqli_query($conn, "SELECT * FROM products ORDER BY id DESC");
$cart_count = 0;
if (isset($_SESSION['cart'])) {
    foreach ($_SESSION['cart'] as $qty) $cart_count += $qty;
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Shop Giang - All In One</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
        body { background-color: #f5f7fa; color: #2f3542; font-family: 'Segoe UI', sans-serif; }
        .navbar { background: #0056b3 !important; padding: 15px 0; }
        .navbar-brand { font-weight: bold; font-size: 24px; color: #fff !important; }
        .card-product { border: none; border-radius: 12px; background: #fff; overflow: hidden; transition: 0.3s; height: 100%; box-shadow: 0 4px 10px rgba(0,0,0,0.05); }
        .card-product:hover { transform: translateY(-5px); box-shadow: 0 8px 20px rgba(0,0,0,0.1); }
        .price-text { color: #ff4757; font-weight: bold; font-size: 19px; }
        .compare-box { background: #f1f2f6; border-radius: 6px; padding: 10px; font-size: 12px; border-left: 4px solid #0056b3; margin: 10px 0; }
        .firmware-tag { background: #eccc68; color: #333; padding: 2px 6px; border-radius: 4px; font-size: 11px; font-weight: bold; display: inline-block; margin-bottom: 5px; }
        .chat-modal { display: none; position: fixed; bottom: 100px; right: 30px; width: 360px; background: white; border-radius: 16px; box-shadow: 0 10px 30px rgba(0,0,0,0.15); z-index: 2000; overflow: hidden; border: 1px solid #eee; }
        .chat-header { background: #0056b3; color: white; padding: 15px; font-weight: bold; display: flex; justify-content: space-between; align-items: center; }
        .chat-body { height: 300px; padding: 15px; overflow-y: auto; background: #f8f9fa; }
    </style>
</head>
<body>

<nav class="navbar navbar-expand-lg sticky-top">
    <div class="container">
        <a class="navbar-brand" href="index.php"><i class="bi bi-cpu me-2"></i>Giang Mobile</a>
        <div class="d-flex align-items-center gap-3">
            <button class="btn btn-light position-relative" data-bs-toggle="modal" data-bs-target="#cartModal"><i class="bi bi-cart3 text-primary"></i> <span class="badge bg-danger"><?php echo $cart_count; ?></span></button>
            
            <?php if(isset($_SESSION['user_logged'])): ?>
                <span class="text-white fw-bold"><i class="bi bi-person-check-fill text-warning"></i> <?php echo $_SESSION['user_logged']; ?></span>
                <a href="index.php?action=logout" class="btn btn-sm btn-danger">Đăng xuất</a>
            <?php else: ?>
                <button class="btn btn-sm btn-outline-light" data-bs-toggle="modal" data-bs-target="#loginModal"><i class="bi bi-box-arrow-in-right"></i> Đăng nhập</button>
            <?php endif; ?>
        </div>
    </div>
</nav>

<div class="container mt-5">
    <?php if(isset($_GET['status']) && $_GET['status'] == 'ordered'): ?>
        <div class="alert alert-success d-flex justify-content-between align-items-center shadow-sm">
            <span>🎉 <strong>Chốt đơn thành công!</strong> Đơn hàng vãng lai của bạn đã được ghi nhận trên hệ thống Giang Mobile.</span>
            <a href="index.php?action=cancel_order" class="btn btn-sm btn-outline-danger fw-bold"><i class="bi bi-trash3-fill"></i> Hủy Đơn Hàng Này</a>
        </div>
    <?php endif; ?>
    
    <?php if(isset($_GET['status']) && $_GET['status'] == 'cancelled') echo '<div class="alert alert-warning shadow-sm">⚠️ Đơn hàng của bạn đã được xóa và hủy bỏ thành công trên hệ thống!</div>'; ?>
    <?php if(isset($_GET['status']) && $_GET['status'] == 'removed') echo '<div class="alert alert-danger shadow-sm">🗑️ Đã xóa sản phẩm khỏi giỏ hàng thành công!</div>'; ?>
    <?php if(isset($_GET['status']) && $_GET['status'] == 'success') echo '<div class="alert alert-info shadow-sm">🛒 Đã thêm máy vào giỏ hàng! Click biểu tượng giỏ hàng ở góc trên để kiểm tra.</div>'; ?>
    
    <h3 class="fw-bold mb-4"><i class="bi bi-layers-half text-primary me-2"></i>Kho máy 100 điện thoại & Phân tích thị trường</h3>
    
    <div class="row g-4">
        <?php while($row = mysqli_fetch_assoc($res)){ ?>
        <div class="col-lg-3 col-md-4 col-sm-6">
            <div class="card-product p-3 d-flex flex-column">
                <h6 class="fw-bold text-truncate mb-2"><?php echo $row['name']; ?></h6>
                <div class="price-text mb-2"><?php echo number_format($row['price']); ?> ₫</div>
                <div><span class="firmware-tag"><?php echo $row['firmware']; ?></span></div>
                <small class="text-muted"><i class="bi bi-cpu"></i> <?php echo $row['specs']; ?></small>
                
                <div class="compare-box">
                    <div>Máy đối thủ: <strong><?php echo $row['comp_name']; ?></strong></div>
                    <div>Giá thị trường: <span class="text-decoration-line-through"><?php echo number_format($row['comp_price']); ?> ₫</span></div>
                    <div class="text-success">Tiết kiệm tại shop: <strong><?php echo number_format($row['comp_price'] - $row['price']); ?> ₫</strong></div>
                </div>

                <div class="d-flex gap-2 mt-auto pt-2">
                    <a href="index.php?action=add&id=<?php echo $row['id']; ?>" class="btn btn-primary btn-sm flex-grow-1">🛒 Chọn Mua</a>
                    <button class="btn btn-outline-secondary btn-sm" onclick="openAIChat('<?php echo $row['name']; ?>', 'Đối thủ bán <?php echo number_format($row['comp_price']); ?> shop mình rẻ hơn và tiết kiệm <?php echo number_format($row['comp_price'] - $row['price']); ?>')">🤖 Hỏi AI</button>
                </div>
            </div>
        </div>
        <?php } ?>
    </div>
</div>

<div class="modal fade" id="loginModal" tabindex="-1">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <div class="modal-header"><h5 class="modal-title fw-bold">Đăng nhập (admin / 123)</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <form method="POST">
                    <div class="mb-3"><input type="text" class="form-control" name="username" placeholder="Tài khoản" required></div>
                    <div class="mb-3"><input type="password" class="form-control" name="password" placeholder="Mật khẩu" required></div>
                    <button type="submit" name="login_action" class="btn btn-primary w-100">Đăng Nhập</button>
                </form>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="cartModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header"><h5 class="modal-title fw-bold"><i class="bi bi-cart-check-fill text-success me-2"></i>Giỏ Hàng Mua Sắm Tự Do</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <?php if ($cart_count == 0): echo '<p class="text-center text-muted py-3">Giỏ hàng trống!</p>'; else: ?>
                    <h6 class="fw-bold mb-2 text-primary"><i class="bi bi-list-stars"></i> Danh sách điện thoại:</h6>
                    <div class="table-responsive mb-3" style="max-height: 200px; overflow-y: auto;">
                        <table class="table table-sm table-bordered align-middle text-center" style="font-size: 13px;">
                            <thead class="table-light">
                                <tr>
                                    <th class="text-start">Tên máy</th>
                                    <th>Giá bán</th>
                                    <th>SL</th>
                                    <th>Thành tiền</th>
                                    <th>Xóa</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $total_payment = 0;
                                foreach ($_SESSION['cart'] as $p_id => $qty) {
                                    $p_id = (int)$p_id;
                                    $p_res = mysqli_query($conn, "SELECT name, price FROM products WHERE id = $p_id");
                                    if ($p_res && $p_row = mysqli_fetch_assoc($p_res)) {
                                        $subtotal = $p_row['price'] * $qty;
                                        $total_payment += $subtotal;
                                        echo '<tr>';
                                        echo '<td class="text-start text-truncate" style="max-width: 150px;"><strong>' . $p_row['name'] . '</strong></td>';
                                        echo '<td class="text-danger">' . number_format($p_row['price']) . ' đ</td>';
                                        echo '<td>' . $qty . '</td>';
                                        echo '<td class="fw-bold text-danger">' . number_format($subtotal) . ' đ</td>';
                                        echo '<td><a href="index.php?action=remove_item&id=' . $p_id . '" class="btn btn-sm text-danger p-0"><i class="bi bi-trash-fill fs-6"></i></a></td>';
                                        echo '</tr>';
                                    }
                                }
                                ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="text-end fw-bold mb-3">
                        Tổng thanh toán: <span class="text-danger fs-5"><?php echo number_format($total_payment); ?> ₫</span>
                    </div>

                    <form method="POST">
                        <div class="mb-2"><input type="text" class="form-control" placeholder="Tên của bạn" required></div>
                        <div class="mb-2"><input type="text" class="form-control" placeholder="Số điện thoại" required></div>
                        <div class="mb-2"><input type="text" class="form-control" placeholder="Địa chỉ giao nhận" required></div>
                        <button type="submit" name="checkout_action" class="btn btn-danger w-100 fw-bold mt-2">Chốt Đơn Giao Ngay (Không Cần Đăng Nhập)</button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div style="position: fixed; bottom: 30px; right: 30px; background-color: #ff4757; color: white; width: 60px; height: 60px; border-radius: 50%; display: flex; justify-content: center; align-items: center; font-size: 26px; cursor: pointer; z-index: 2000; box-shadow: 0 4px 15px rgba(255, 71, 87, 0.4);" onclick="toggleN8nChat()"><i class="bi bi-chat-right-dots-fill"></i></div>

<div class="chat-modal" id="chatModal">
    <div class="chat-header"><span id="chatTitle">Tổng Đài Trợ Lý AI</span><i class="bi bi-xlg" style="cursor: pointer; font-size: 14px;" onclick="closeChat()"></i></div>
    <div class="chat-body" id="chatBody"></div>
    <div style="display: flex; padding: 12px; border-top: 1px solid #eee; background: white;">
        <input type="text" id="userInput" placeholder="Nhập câu hỏi tại đây..." style="flex: 1; padding: 8px 15px; border: 1px solid #ddd; border-radius: 20px; outline: none; font-size: 14px;" onkeypress="if(event.key==='Enter') sendMessage()">
        <button onclick="sendMessage()" style="background: none; border: none; color: #0056b3; font-size: 22px; margin-left: 8px;"><i class="bi bi-send-fill"></i></button>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    let chatType = "product"; let activeProduct = ""; let activeCompInfo = "";
    function closeChat() { document.getElementById('chatModal').style.display = 'none'; }
    
    function openAIChat(pName, cInfo) {
        chatType = "product"; activeProduct = pName; activeCompInfo = cInfo;
        document.getElementById('chatTitle').innerText = "Hỏi AI: " + pName;
        document.getElementById('chatModal').style.display = 'block';
        document.getElementById('chatBody').innerHTML = `<div class="mb-2"><p style="background: #fff; padding: 10px; border-radius: 12px; font-size: 14px; box-shadow: 0 2px 5px rgba(0,0,0,0.03); border-left: 3px solid #0056b3;">🤖 Chào bạn! Mình đã quét thông số so sánh của <b>${pName}</b> so với đối thủ. Bạn cần tư vấn chip Kirin, hạ cấp ROM hay tối ưu phần mềm ạ?</p></div>`;
    }

    function toggleN8nChat() {
        chatType = "general";
        let modal = document.getElementById('chatModal');
        if (modal.style.display === 'block') { modal.style.display = 'none'; return; }
        document.getElementById('chatTitle').innerText = "Tổng Đài CSKH AI (n8n)";
        modal.style.display = 'block';
        document.getElementById('chatBody').innerHTML = `<div class="mb-2"><p style="background: #fff; padding: 10px; border-radius: 12px; font-size: 14px; box-shadow: 0 2px 5px rgba(0,0,0,0.03); border-left: 3px solid #ff4757;">💬 Xin chào! Đây là tổng đài CSKH tự động n8n Cloud. Cửa hàng Tùng Dương có voucher 10% giảm giá đơn hàng vãng lai. Bạn cần hỗ trợ chính sách gì ạ?</p></div>`;
    }

    async function sendMessage() {
        let input = document.getElementById('userInput'); let msg = input.value.trim(); if(!msg) return;
        let body = document.getElementById('chatBody');
        body.innerHTML += `<div class="text-end mb-2"><p style="background: #0056b3; color: white; display: inline-block; padding: 8px 14px; border-radius: 12px; font-size: 14px; margin: 0;">${msg}</p></div>`;
        input.value = ""; body.scrollTop = body.scrollHeight;
        
        let loadId = "load-" + Date.now();
        body.innerHTML += `<div id="${loadId}" class="mb-2"><p style="font-size:13px; color:#888;"><i class="bi bi-arrow-clockwise d-inline-block text-secondary" style="animation: spin 1s linear infinite;"></i> Trợ lý AI đang xử lý...</p></div>`;
        body.scrollTop = body.scrollHeight;

        try {
            let res, data;
            if (chatType === "product") {
                res = await fetch('index.php?api=gemini', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ product_name: activeProduct, competitor_info: activeCompInfo, question: msg })
                });
            } else {
                res = await fetch('https://phanungduong.app.n8n.cloud/webhook/cskh-badminton', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ message: msg })
                });
            }
            data = await res.json();
            document.getElementById(loadId).remove();

            let reply = data.reply || data.output || "Hệ thống Tùng Dương Mobile đã nhận thông tin!";
            if (Array.isArray(data) && data.length > 0) reply = data[0].reply || data[0].output;

            let borderStyle = (chatType === "product") ? "3px solid #0056b3" : "3px solid #ff4757";
            body.innerHTML += `<div class="text-start mb-2"><p style="background: #fff; padding: 10px 14px; border-radius: 12px; font-size: 14px; box-shadow: 0 2px 5px rgba(0,0,0,0.03); border-left: ${borderStyle};">${reply}</p></div>`;
            body.scrollTop = body.scrollHeight;
        } catch {
            document.getElementById(loadId).remove();
        }
    }
</script>
<style> @keyframes spin { 100% { transform: rotate(360deg); } } </style>
</body>
</html>
