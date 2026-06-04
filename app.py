from flask import Flask, render_template, request, jsonify, session, redirect, url_for
import google.generativeai as genai
import random

app = Flask(__name__)
app.secret_key = 'tung_duong_mobile_bi_mat_2026'

# --- CẤU HÌNH API GOOGLE GEMINI (BƠM NÃO CHO NÚT HỎI AI) ---
# Dương điền API Key thật của bạn vào đây (Bắt đầu bằng AIzaSy...) để kích hoạt AI nhé
GEMINI_API_KEY = "AIzaSy_ĐIỀN_MÃ_API_CỦA_BẠN"
genai.configure(api_key=GEMINI_API_KEY)
model = genai.GenerativeModel('gemini-2.5-flash')

# KHỞI TẠO DỮ LIỆU ĐỦ 100 SẢN PHẨM PHÂN TÍCH ĐỐI THỦ (Yêu cầu 6 & 7)
def get_100_products():
    brands = ["Honor 10", "Honor 20 Pro", "Huawei P30", "Huawei P40 Pro", "Huawei Mate 30", "Honor Magic 5", "Huawei Nova 7i"]
    chips = ["Kirin 970, RAM 6GB", "Kirin 980, RAM 8GB", "Kirin 990 5G, RAM 8GB", "Snapdragon 8 Gen 2, RAM 12GB"]
    roms = ["EMUI 8.1 (Hạ cấp OK)", "EMUI 11 (Hỗ trợ GBox)", "HarmonyOS 2.0", "MagicOS 7.1"]
    competitors = {
        "Honor 10": {"name": "Xiaomi Redmi Note 12", "price": 4500000},
        "Honor 20 Pro": {"name": "Samsung Galaxy A54", "price": 7200000},
        "Huawei P30": {"name": "iPhone 11 cũ", "price": 6500000},
        "Huawei P40 Pro": {"name": "iPhone 12 Pro", "price": 11500000},
        "Huawei Mate 30": {"name": "Samsung Galaxy S20 FE", "price": 8000000},
        "Honor Magic 5": {"name": "Xiaomi 13T", "price": 12000000},
        "Huawei Nova 7i": {"name": "Oppo Reno8 Z", "price": 5900000}
    }
    
    products = []
    random.seed(42) # Giữ cố định danh sách không bị đảo lộn khi F5 trên Render
    for i in range(1, 101):
        base = random.choice(brands)
        comp = competitors[base]
        shop_price = random.randint(30, 110) * 100000
        comp_price = shop_price + random.randint(10, 30) * 100000
        
        products.append({
            "id": i,
            "name": f"{base} (Bản {i})",
            "price": shop_price,
            "specs": random.choice(chips),
            "firmware": random.choice(roms),
            "comp_name": comp["name"],
            "comp_price": comp_price
        })
    return products

@app.route('/')
def index():
    products = get_100_products()
    return render_template('index.html', products=products, user=session.get('user_profile'))

# ĐĂNG NHẬP LẤY THÔNG TIN PROFILE CÓ SẴN (Yêu cầu 9)
@app.route('/login', methods=['POST'])
def login():
    username = request.form.get('username')
    password = request.form.get('password')
    if username == 'admin' and password == '123':
        session['user_profile'] = {
            "name": "Phan Tùng Dương",
            "phone": "0988123456",
            "address": "Phố Tạ Quang Bửu, Hai Bà Trưng, Hà Nội"
        }
    return redirect(url_for('index'))

@app.route('/logout')
def logout():
    session.pop('user_profile', None)
    return redirect(url_for('index'))

# AI CHI TIẾT TỪNG SẢN PHẨM + ĐÍNH KÈM LINK (Yêu cầu 1 & 3)
@app.route('/ask-product-ai', methods=['POST'])
def ask_product_ai():
    data = request.json
    p_id = data.get('product_id')
    p_name = data.get('product_name')
    question = data.get('question')
    
    product_link = f"{request.host_url}?search={p_name.replace(' ', '+')}"
    
    prompt = f"""
    Annyeong! Bạn là chuyên gia tư vấn điện thoại tại shop Tùng Dương Mobile.
    Khách đang xem máy: '{p_name}'. Khách hỏi: '{question}'
    Nhiệm vụ: Hãy tư vấn kỹ thuật (Hạ cấp ROM, cài Google, GBox) thật chuyên sâu.
    BẮT BUỘC chèn nguyên văn đoạn text này vào cuối câu trả lời: 'Bạn có thể xem lại cấu hình máy tại đây: {product_link}'.
    Trả lời ngắn gọn trong 2-3 câu. Xưng 'Mình', gọi khách là 'Bạn'.
    """
    try:
        response = model.generate_content(prompt)
        reply = response.text.replace("**", "")
        return jsonify({"reply": reply})
    except Exception:
        return jsonify({"reply": f"Hệ thống bận, bạn xem máy tại đây nhé: {product_link}"})

if __name__ == '__main__':
    app.run(debug=True)
