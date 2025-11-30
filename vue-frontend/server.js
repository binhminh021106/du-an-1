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

// --- KEY CỦA BẠN ---
const API_KEY = "AIzaSyAxlCPXF0PJjsAs3hY8vN7WOOHvYY8B1ys"; 

const genAI = new GoogleGenerativeAI(API_KEY);

// [CẬP NHẬT QUAN TRỌNG]: Dạy AI bỏ qua các từ chung chung
const SYSTEM_INSTRUCTION = `
Bạn là trợ lý ảo tìm kiếm sản phẩm. 
Output MẶC ĐỊNH là JSON thuần: {"keyword": "...", "min_price": ..., "max_price": ...}.
Không trả lời bằng lời văn. Chỉ JSON.

QUY TẮC QUAN TRỌNG:
1. Nếu người dùng dùng từ chung chung như "sản phẩm", "đồ", "hàng", "cái gì", "gợi ý", "tìm"... thì keyword PHẢI là null (hoặc chuỗi rỗng). CHỈ lấy tên cụ thể (ví dụ: "giày", "laptop", "chuột").
2. Xử lý giá: "k" = 000, "tr", "triệu" = 000000.

Ví dụ: 
- "sản phẩm dưới 100k" -> {"keyword": null, "max_price": 100000}
- "tìm giày dưới 200k" -> {"keyword": "giày", "max_price": 200000}
`;

// --- HÀM TỰ ĐỘNG LẤY DANH SÁCH MODEL TỪ GOOGLE ---
async function getValidModel() {
    try {
        console.log("🔍 Đang hỏi Google xem Key này dùng được Model nào...");
        const response = await fetch(`https://generativelanguage.googleapis.com/v1beta/models?key=${API_KEY}`);
        const data = await response.json();

        if (data.error) {
            console.error("❌ Lỗi API Key:", data.error.message);
            return null;
        }

        const availableModels = data.models
            .filter(m => m.supportedGenerationMethods && m.supportedGenerationMethods.includes("generateContent"))
            .map(m => m.name.replace("models/", ""));

        if (availableModels.length > 0) {
            console.log("✅ Các Model khả dụng:", availableModels.join(", "));
            
            // Ưu tiên chọn Pro chuẩn, sau đó đến các bản khác
            const preferred = availableModels.find(m => m === "gemini-1.5-pro") || 
                              availableModels.find(m => m === "gemini-1.5-pro-latest") ||
                              availableModels.find(m => m === "gemini-pro") ||
                              availableModels.find(m => m.includes("flash")); 
            
            console.log("🚀 Đã chọn Model:", preferred);
            return preferred;
        } else {
            console.error("❌ Không tìm thấy Model nào khả dụng cho Key này!");
            return null;
        }
    } catch (e) {
        console.error("❌ Lỗi kết nối lấy danh sách model:", e.message);
        return "gemini-1.5-pro"; 
    }
}

app.post('/api/chat-search', async (req, res) => {
    try {
        const { query } = req.body;
        console.log("👉 Nhận câu hỏi:", query);

        const currentModelName = await getValidModel();
        if (!currentModelName) {
            throw new Error("Không tìm thấy Model AI nào hoạt động với Key này.");
        }

        const model = genAI.getGenerativeModel({ model: currentModelName });
        const prompt = `${SYSTEM_INSTRUCTION}\nUser: "${query}"\nJSON:`;
        
        const result = await model.generateContent(prompt);
        const response = await result.response;
        
        let text = response.text();
        text = text.replace(/```json/g, '').replace(/```/g, '').trim();
        
        let filters = {};
        try {
            filters = JSON.parse(text);
            console.log("✅ AI đã hiểu (JSON):", filters);
        } catch (e) {
            filters = { keyword: null }; // Nếu lỗi thì không lọc theo tên
        }

        res.json({ ai_data: filters });

    } catch (error) {
        console.error("❌ LỖI NGHIÊM TRỌNG:", error.message);
        res.status(500).json({ error: "Lỗi Server AI", details: error.message });
    }
});

app.listen(PORT, () => {
    console.log(`\n!!! SERVER ĐÃ CẬP NHẬT LOGIC TỪ KHÓA !!!`);
    console.log(`Server: http://localhost:${PORT}`);
    getValidModel(); 
});