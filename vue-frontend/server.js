import 'dotenv/config';
import express from 'express';
import bodyParser from 'body-parser';
import cors from 'cors';
import { GoogleGenerativeAI } from "@google/generative-ai";

const app = express();
const PORT = 3000;

app.use(cors({
    origin: '*',
    methods: ['GET', 'POST'],
    allowedHeaders: ['Content-Type']
}));
app.use(bodyParser.json());

// --- CẤU HÌNH API KEY ---
const API_KEY = "AIzaSyBjMweCPWXHKWaJMIqujo1M6MAejnwAv20"; 

const genAI = new GoogleGenerativeAI(API_KEY);

// Hàm chọn model phù hợp nhất
async function getValidModel() {
    try {
        const response = await fetch(`https://generativelanguage.googleapis.com/v1beta/models?key=${API_KEY}`);
        const data = await response.json();
        
        // Ưu tiên model 1.5 Pro hoặc Flash
        const preferred = data.models?.find(m => m.name.includes("gemini-1.5-pro")) || 
                          data.models?.find(m => m.name.includes("gemini-1.5-flash"));
                          
        return preferred ? preferred.name.replace("models/", "") : "gemini-1.5-flash";
    } catch (e) {
        return "gemini-1.5-flash"; 
    }
}

app.post('/api/chat-search', async (req, res) => {
    try {
        // [QUAN TRỌNG] Nhận Context từ Client gửi lên
        const { query, categories, brands } = req.body;
        console.log(`👉 User: "${query}"`);
        
        // Tạo chuỗi danh sách hợp lệ để "ép" AI chọn
        const validCategories = categories && categories.length > 0 ? categories.join(", ") : "Không có";
        const validBrands = brands && brands.length > 0 ? brands.join(", ") : "Không có";

        const SYSTEM_INSTRUCTION = `
        Bạn là API trích xuất ý định tìm kiếm sản phẩm thương mại điện tử.
        
        DỮ LIỆU THỰC TẾ CỦA SHOP (CONTEXT):
        - Danh mục có sẵn: [${validCategories}]
        - Thương hiệu có sẵn: [${validBrands}]

        NHIỆM VỤ:
        Phân tích query: "${query}" -> Trả về JSON bộ lọc.

        QUY TẮC MAPPING THÔNG MINH:
        1. Mapping Danh mục (Category):
           - User tìm "đt", "dế", "mobile", "smartphone" -> Chọn giá trị trong [Danh mục có sẵn] gần nghĩa nhất (VD: "Điện thoại di động").
           - "lap", "máy tính" -> Chọn (VD: "Laptop & Macbook").
           - "tai nghe", "headphone" -> Chọn (VD: "Thiết bị âm thanh").
        
        2. Mapping Thương hiệu (Brand):
           - "táo", "nhà táo", "ip" -> Nếu có "Apple" trong [Thương hiệu có sẵn], chọn "brand_name": "Apple".
           - "ss", "sam" -> "Samsung".
        
        3. Xử lý xung đột Keyword:
           - Nếu từ khóa đã được xác định là Brand hoặc Category -> KHÔNG đưa nó vào trường "keyword" nữa.
           - Ví dụ: "Tìm laptop Dell" -> category_name="Laptop", brand_name="Dell", keyword=null. (Vì đã lọc đủ ý).
           - Ví dụ: "Tìm laptop gaming" -> category_name="Laptop", keyword="gaming".

        OUTPUT JSON:
        {
          "keyword": string | null,       // Chỉ chứa tên model cụ thể (VD: "S24 Ultra", "Pro Max")
          "category_name": string | null, // Bắt buộc phải giống text trong [Danh mục có sẵn]
          "brand_name": string | null,    // Bắt buộc phải giống text trong [Thương hiệu có sẵn]
          "min_price": number | null,
          "max_price": number | null
        }
        
        Chỉ trả về JSON. Không giải thích thêm.
        `;

        const modelName = await getValidModel();
        const model = genAI.getGenerativeModel({ model: modelName });
        
        const result = await model.generateContent(SYSTEM_INSTRUCTION);
        const response = await result.response;
        
        let text = response.text();
        // Vệ sinh JSON
        text = text.replace(/```json/g, '').replace(/```/g, '').trim();
        if (text.startsWith('JSON')) text = text.replace('JSON', '').trim();
        
        let filters = {};
        try {
            filters = JSON.parse(text);
            console.log("✅ AI Filter:", filters);
        } catch (e) {
            console.error("⚠️ AI lỗi JSON, dùng fallback keyword.");
            filters = { keyword: query };
        }

        res.json({ ai_data: filters });

    } catch (error) {
        console.error("❌ Lỗi Server:", error.message);
        res.status(500).json({ error: "Lỗi xử lý AI" });
    }
});

app.listen(PORT, () => {
    console.log(`\n>>> SERVER CONTEXT AWARE ĐANG CHẠY TẠI PORT ${PORT} <<<`);
});