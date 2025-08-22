 const express = require("express");
const axios = require("axios");
const bodyParser = require("body-parser");

const app = express();
app.use(bodyParser.json());

// 🔹 Configurações do WhatsApp Cloud API
const WHATSAPP_TOKEN = "SEU_PERMANENT_ACCESS_TOKEN";
const PHONE_NUMBER_ID = "SEU_PHONE_NUMBER_ID";
const RECIPIENT_NUMBER = "5511999999999"; // número no formato internacional, sem "+"

// Endpoint para enviar PDF
app.post("/send-pdf", async (req, res) => {
    const pdfUrl = req.body.pdfUrl; // URL pública do PDF
    const caption = req.body.caption || "Segue o relatório em PDF 📄";

    if (!pdfUrl) {
        return res.status(400).json({ error: "URL do PDF é obrigatória" });
    }

    try {
        const response = await axios.post(
            `https://graph.facebook.com/v20.0/${PHONE_NUMBER_ID}/messages`,
            {
                messaging_product: "whatsapp",
                to: RECIPIENT_NUMBER,
                type: "document",
                document: {
                    link: pdfUrl,
                    caption: caption
                }
            },
            {
                headers: {
                    Authorization: `Bearer ${WHATSAPP_TOKEN}`,
                    "Content-Type": "application/json"
                }
            }
        );

        res.json({ success: true, response: response.data });
    } catch (error) {
        console.error("Erro ao enviar PDF:", error.response?.data || error.message);
        res.status(500).json({ success: false, error: error.message });
    }
});

app.listen(3000, () => {
    console.log("Servidor rodando na porta 3000");
});
