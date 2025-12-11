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
const API_KEY = process.env.GEMINI_API_KEY || "AIzaSyDGAEVitlEFcIvQdKYc1hfUF7arAwD9mw8"; 
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
        // [CẬP NHẬT] Nhận thêm 'history' từ client để hỗ trợ trò chuyện liên tục
        const { query, categories, brands, history } = req.body;
        console.log(`👉 User: "${query}"`);
        
        const now = new Date();
        const timeString = now.toLocaleString("vi-VN", { timeZone: "Asia/Ho_Chi_Minh" });
        const currentMonth = now.getMonth() + 1;
        const currentDay = now.getDate();

        const validCategories = categories && categories.length > 0 ? categories.join(", ") : "Không có";
        const validBrands = brands && brands.length > 0 ? brands.join(", ") : "Không có";

        const SYSTEM_INSTRUCTION = `
        Bạn là ThinkBot, nhân viên bán hàng ảo thân thiện, thông minh của cửa hàng công nghệ ThinkHub.
        
        THÔNG TIN NGỮ CẢNH:
        - Thời gian hiện tại: ${timeString}.
        - Danh mục có sẵn: [${validCategories}]
        - Thương hiệu có sẵn: [${validBrands}]

        NHIỆM VỤ CỦA BẠN:
        1. **Trò chuyện & Hướng dẫn:**
           - Luôn trả lời lịch sự, ngắn gọn, có thể dùng emoji 😊.
           - Nếu người dùng chào hỏi, hãy chào lại và gợi ý sản phẩm HOT.
           - Nếu hôm nay là ngày lễ (24-25/12 Noel, 1/1 Tết Dương, 14/2 Valentine...), hãy tự động thêm lời chúc phù hợp vào đầu câu trả lời.
        
        2. **Phân tích Tìm kiếm (Quan trọng):**
           - Nếu người dùng có ý định tìm mua, hỏi giá, so sánh -> xác định intent: "search".
           - Trích xuất bộ lọc (filters) thật thông minh.

        QUY TẮC XỬ LÝ DỮ LIỆU (MAPPING):
        - **Giá tiền:** Hiểu mọi định dạng "teencode":
          + "2 củ", "2tr", "2 triệu", "2000k" -> 2000000
          + "200k", "200 nghìn" -> 200000
          + "dưới 5 củ" -> max_price: 5000000
          + "trên 10tr" -> min_price: 10000000
        
        - **Danh mục & Thương hiệu:**
          + "điện thoại", "dế", "mobile" -> Chọn category gần nhất (VD: "Điện thoại di động").
          + "lap", "máy tính xách tay" -> Chọn category (VD: "Laptop & Macbook").
          + "táo", "nhà táo" -> brand_name: "Apple".

        OUTPUT JSON FORM:
        {
          "intent": "search" | "chat",
          "reply": string, // Câu trả lời của bạn (bao gồm cả lời chúc lễ nếu có)
          "filters": {
             "keyword": string | null,       // Từ khóa tên sản phẩm (VD: "gaming", "S24")
             "category_name": string | null, // Phải khớp chính xác text trong [Danh mục có sẵn]
             "brand_name": string | null,    // Phải khớp chính xác text trong [Thương hiệu có sẵn]
             "min_price": number | null,
             "max_price": number | null,
             "sort": "price_asc" | "price_desc" | "newest" | null
          }
        }
        `;

        const modelName = await getValidModel();
        const model = genAI.getGenerativeModel({ 
            model: modelName,
            systemInstruction: SYSTEM_INSTRUCTION 
        });
        
        // [CẬP NHẬT] Xử lý lịch sử chat để AI nhớ ngữ cảnh
        let chatHistory = [];
        if (history && Array.isArray(history)) {
            chatHistory = history.map(msg => ({
                role: msg.role === 'ai' ? 'model' : 'user', // Gemini dùng 'model', Client dùng 'ai'
                parts: [{ text: msg.text }]
            }));
        }

        const chat = model.startChat({
            history: chatHistory,
            generationConfig: { responseMimeType: "application/json" }
        });

        const result = await chat.sendMessage(query);
        const response = await result.response;
        
        let text = response.text();
        // Vệ sinh JSON (phòng trường hợp AI trả về markdown code block)
        text = text.replace(/```json/g, '').replace(/```/g, '').trim();
        
        let aiResult = {};
        try {
            aiResult = JSON.parse(text);
            console.log("✅ AI Intent:", aiResult.intent);
        } catch (e) {
            console.error("⚠️ AI JSON Parse Error:", e);
            aiResult = { 
                intent: 'search', 
                reply: 'Dạ mình chưa nghe rõ, nhưng mình tìm thấy các sản phẩm này có thể bạn thích:',
                filters: { keyword: query } 
            };
        }

        res.json({ ai_data: aiResult });

    } catch (error) {
        console.error("❌ Lỗi Server:", error.message);
        res.status(200).json({ // Trả về 200 để Frontend không bị crash, chỉ hiện lỗi chat
            ai_data: { 
                intent: 'chat', 
                reply: "Xin lỗi, hiện tại server AI đang quá tải một chút. Bạn thử lại sau giây lát nhé! 🤯" 
            } 
        });
    }
});

app.listen(PORT, () => {
    console.log(`\n>>> SERVER THINKBOT (Context-Aware) ĐANG CHẠY TẠI PORT ${PORT} <<<`);
});