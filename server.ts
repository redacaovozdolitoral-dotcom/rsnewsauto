import express from "express";
import { createServer as createViteServer } from "vite";
import path from "path";
import { fileURLToPath } from "url";
import Database from "better-sqlite3";
import Parser from "rss-parser";
import axios from "axios";
import * as cheerio from "cheerio";
import { GoogleGenAI } from "@google/genai";

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);

const db = new Database("database.sqlite");
const parser = new Parser();

// Initialize Gemini
const ai = new GoogleGenAI({ apiKey: process.env.GEMINI_API_KEY || "" });

// Initialize Database
db.exec(`
  CREATE TABLE IF NOT EXISTS tenants (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL,
    wp_url TEXT,
    wp_user TEXT,
    wp_app_password TEXT,
    meta_access_token TEXT,
    fb_page_id TEXT,
    ig_user_id TEXT,
    fb_scraper_user TEXT,
    fb_scraper_pass TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
  );

  CREATE TABLE IF NOT EXISTS feeds (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    tenant_id INTEGER,
    name TEXT NOT NULL,
    url TEXT NOT NULL,
    type TEXT DEFAULT 'rss', -- 'rss', 'web', 'facebook'
    keywords TEXT, -- Comma separated
    last_scraped DATETIME,
    FOREIGN KEY(tenant_id) REFERENCES tenants(id)
  );

  CREATE TABLE IF NOT EXISTS posts (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    tenant_id INTEGER,
    title TEXT,
    original_url TEXT UNIQUE,
    content TEXT,
    image_url TEXT,
    ai_content TEXT,
    status TEXT DEFAULT 'pending',
    published_at DATETIME,
    FOREIGN KEY(tenant_id) REFERENCES tenants(id)
  );
`);

async function startServer() {
  const app = express();
  const PORT = 3000;

  app.use(express.json());

  // API Routes
  app.get("/api/tenants", (req, res) => {
    const tenants = db.prepare("SELECT * FROM tenants").all();
    res.json(tenants);
  });

  app.post("/api/tenants", (req, res) => {
    const { name, wp_url, wp_user, wp_app_password, meta_access_token, fb_page_id, ig_user_id, fb_scraper_user, fb_scraper_pass } = req.body;
    const info = db.prepare(`
      INSERT INTO tenants (name, wp_url, wp_user, wp_app_password, meta_access_token, fb_page_id, ig_user_id, fb_scraper_user, fb_scraper_pass)
      VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
    `).run(name, wp_url, wp_user, wp_app_password, meta_access_token, fb_page_id, ig_user_id, fb_scraper_user, fb_scraper_pass);
    res.json({ id: info.lastInsertRowid });
  });

  app.get("/api/feeds", (req, res) => {
    const feeds = db.prepare("SELECT f.*, t.name as tenant_name FROM feeds f JOIN tenants t ON f.tenant_id = t.id").all();
    res.json(feeds);
  });

  app.post("/api/feeds", (req, res) => {
    const { tenant_id, name, url, type, keywords } = req.body;
    const info = db.prepare("INSERT INTO feeds (tenant_id, name, url, type, keywords) VALUES (?, ?, ?, ?, ?)").run(tenant_id, name, url, type || 'rss', keywords);
    res.json({ id: info.lastInsertRowid });
  });

  app.get("/api/posts", (req, res) => {
    const posts = db.prepare("SELECT p.*, t.name as tenant_name FROM posts p JOIN tenants t ON p.tenant_id = t.id ORDER BY p.id DESC LIMIT 50").all();
    res.json(posts);
  });

  // Scrape Logic
  app.post("/api/scrape", async (req, res) => {
    const feeds = db.prepare("SELECT * FROM feeds").all();
    let totalNew = 0;

    for (const feed of feeds as any[]) {
      try {
        let items: any[] = [];
        
        if (feed.type === 'rss') {
          const feedData = await parser.parseURL(feed.url);
          items = feedData.items.map(i => ({ title: i.title, link: i.link, content: i.contentSnippet || i.content }));
        } else if (feed.type === 'web') {
          // Basic web scraping for "common sites"
          const { data } = await axios.get(feed.url);
          const $ = cheerio.load(data);
          // This is a generic selector, in a real app you'd customize per site
          $('article, .post, .entry').each((i, el) => {
            const title = $(el).find('h1, h2, h3').first().text().trim();
            const link = $(el).find('a').first().attr('href');
            if (title && link) {
              items.push({ title, link: link.startsWith('http') ? link : new URL(link, feed.url).href, content: $(el).text().trim() });
            }
          });
        }

        // Filter by keywords if present
        if (feed.keywords) {
          const kwList = feed.keywords.split(',').map((k: string) => k.trim().toLowerCase());
          items = items.filter(item => {
            const text = `${item.title} ${item.content}`.toLowerCase();
            return kwList.some(kw => text.includes(kw));
          });
        }

        for (const item of items) {
          const exists = db.prepare("SELECT id FROM posts WHERE original_url = ?").get(item.link);
          if (!exists) {
            // Process with AI (Gemini as default engine)
            let aiContent = "";
            try {
              const prompt = `Você é um editor de notícias profissional. Reescreva esta notícia para um post de rede social (Facebook/Instagram). 
              Use emojis, hashtags relevantes e um título chamativo.
              Título Original: ${item.title}
              Conteúdo Original: ${item.content}`;
              
              const result = await ai.models.generateContent({
                model: "gemini-3.1-pro-preview",
                contents: prompt
              });
              aiContent = result.text || "";
            } catch (aiErr) {
              console.error("AI Error:", aiErr);
              aiContent = item.content || "";
            }

            db.prepare(`
              INSERT INTO posts (tenant_id, title, original_url, content, ai_content, status)
              VALUES (?, ?, ?, ?, ?, 'pending')
            `).run(feed.tenant_id, item.title, item.link, item.content, aiContent);
            totalNew++;
          }
        }
        db.prepare("UPDATE feeds SET last_scraped = CURRENT_TIMESTAMP WHERE id = ?").run(feed.id);
      } catch (err) {
        console.error(`Error scraping feed ${feed.url}:`, err);
      }
    }
    res.json({ success: true, new_posts: totalNew });
  });

  // Publish Logic (Meta API)
  app.post("/api/publish/:postId", async (req, res) => {
    const { postId } = req.params;
    const post = db.prepare(`
      SELECT p.*, t.meta_access_token, t.fb_page_id, t.ig_user_id 
      FROM posts p 
      JOIN tenants t ON p.tenant_id = t.id 
      WHERE p.id = ?
    `).get(postId) as any;

    if (!post || !post.meta_access_token || !post.fb_page_id) {
      return res.status(400).json({ error: "Post or Meta credentials missing" });
    }

    try {
      // Publish to Facebook
      const fbUrl = `https://graph.facebook.com/v21.0/${post.fb_page_id}/feed`;
      await axios.post(fbUrl, {
        message: post.ai_content,
        link: post.original_url,
        access_token: post.meta_access_token
      });

      // Update status
      db.prepare("UPDATE posts SET status = 'published', published_at = CURRENT_TIMESTAMP WHERE id = ?").run(postId);
      res.json({ success: true });
    } catch (err: any) {
      console.error("Publishing Error:", err.response?.data || err.message);
      res.status(500).json({ error: "Failed to publish to Meta", details: err.response?.data });
    }
  });

  // Vite middleware for development
  if (process.env.NODE_ENV !== "production") {
    const vite = await createViteServer({
      server: { middlewareMode: true },
      appType: "spa",
    });
    app.use(vite.middlewares);
  } else {
    app.use(express.static(path.join(__dirname, "dist")));
    app.get("*", (req, res) => {
      res.sendFile(path.join(__dirname, "dist", "index.html"));
    });
  }

  app.listen(PORT, "0.0.0.0", () => {
    console.log(`Server running on http://localhost:${PORT}`);
    
    // Automatic Scrape every 30 minutes
    setInterval(async () => {
      console.log(`[${new Date().toISOString()}] Starting automatic scrape...`);
      try {
        const res = await axios.post(`http://localhost:${PORT}/api/scrape`);
        console.log(`[${new Date().toISOString()}] Auto-scrape complete:`, res.data);
      } catch (err) {
        console.error(`[${new Date().toISOString()}] Auto-scrape failed:`, err);
      }
    }, 30 * 60 * 1000);
  });
}

startServer();
