<!doctype html>
<html lang="zh-Hant">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>TruthShield API</title>
    <style>
      :root {
        color-scheme: dark;
        font-family: Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
        background: #09090b;
        color: #f4f4f5;
      }

      body {
        min-height: 100vh;
        margin: 0;
        display: grid;
        place-items: center;
        background:
          linear-gradient(135deg, rgba(103, 232, 249, 0.14), transparent 34%),
          radial-gradient(circle at 76% 18%, rgba(248, 113, 113, 0.18), transparent 28%),
          #09090b;
      }

      main {
        width: min(720px, calc(100% - 40px));
        border: 1px solid rgba(255, 255, 255, 0.12);
        border-radius: 14px;
        background: rgba(24, 24, 27, 0.84);
        padding: 36px;
        box-shadow: 0 24px 80px rgba(0, 0, 0, 0.38);
      }

      .eyebrow {
        color: #67e8f9;
        font-size: 13px;
        font-weight: 700;
        letter-spacing: .04em;
      }

      h1 {
        margin: 12px 0 0;
        font-size: clamp(32px, 6vw, 52px);
        line-height: 1.08;
      }

      p {
        color: #a1a1aa;
        line-height: 1.8;
      }

      .links {
        display: flex;
        flex-wrap: wrap;
        gap: 10px;
        margin-top: 26px;
      }

      a {
        border: 1px solid rgba(255, 255, 255, 0.12);
        border-radius: 8px;
        color: #f4f4f5;
        padding: 10px 14px;
        text-decoration: none;
      }

      a.primary {
        background: #67e8f9;
        border-color: #67e8f9;
        color: #09090b;
        font-weight: 800;
      }
    </style>
  </head>
  <body>
    <main>
      <div class="eyebrow">TruthShield API</div>
      <h1>真相護盾後端服務</h1>
      <p>
        這裡提供後台、新聞狀態 API、插件資料、投票結算、透明治理、捐款 callback 與營運健康檢查。
        官網前端請使用 Vue 專案提供的本地服務。
      </p>
      <div class="links">
        <a class="primary" href="/admin">前往後台</a>
        <a href="/api/system/health">系統健康</a>
        <a href="/api/docs">API 文件</a>
      </div>
    </main>
  </body>
</html>
