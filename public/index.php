<?php

declare(strict_types=1);

use PumpManager\App;
use PumpManager\Http\Request;
use PumpManager\Support\EnvLoader;

$autoload = dirname(__DIR__) . '/vendor/autoload.php';
if (is_file($autoload)) {
    require $autoload;
} else {
    spl_autoload_register(static function (string $class): void {
        $prefix = 'PumpManager\\';
        if (!str_starts_with($class, $prefix)) {
            return;
        }

        $relative = substr($class, strlen($prefix));
        $path = dirname(__DIR__) . '/src/' . str_replace('\\', '/', $relative) . '.php';
        if (is_file($path)) {
            require $path;
        }
    });
}

EnvLoader::load(dirname(__DIR__) . '/.env');

$request = Request::fromGlobals();
if (str_starts_with($request->path, '/api/')) {
    (new App(dirname(__DIR__) . '/config/config.php'))->handle($request)->send();
    return;
}

?><!doctype html>
<html lang="zh-CN">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>顶峰永磁高速深井泵 IoT 管理控制台</title>
  <style>
    :root {
      color-scheme: light;
      font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
      --bg: #f4f7fb;
      --card: #fff;
      --text: #182235;
      --muted: #667085;
      --primary: #0f62fe;
      --danger: #d92d20;
      --border: #d9e2ef;
      --ok: #039855;
    }

    * { box-sizing: border-box; }
    body { margin: 0; background: var(--bg); color: var(--text); }
    header { padding: 28px 32px; background: linear-gradient(135deg, #082b66, #0f62fe); color: #fff; }
    header p { max-width: 900px; margin: 8px 0 0; color: #d7e7ff; }
    main { max-width: 1180px; margin: 0 auto; padding: 24px; display: grid; gap: 20px; }
    .grid { display: grid; grid-template-columns: 1.1fr .9fr; gap: 20px; }
    .card { background: var(--card); border: 1px solid var(--border); border-radius: 16px; padding: 20px; box-shadow: 0 10px 26px rgba(16, 24, 40, .06); }
    h1, h2, h3 { margin-top: 0; }
    label { display: grid; gap: 6px; font-weight: 600; color: #344054; }
    input, select, textarea, button {
      width: 100%;
      border: 1px solid var(--border);
      border-radius: 10px;
      padding: 10px 12px;
      font: inherit;
    }
    textarea { min-height: 100px; resize: vertical; font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace; }
    button { cursor: pointer; border: 0; background: var(--primary); color: #fff; font-weight: 700; }
    button.secondary { background: #e8f0ff; color: #0b3d91; }
    button.danger { background: var(--danger); }
    .form-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 14px; }
    .full { grid-column: 1 / -1; }
    .pump-list { display: grid; gap: 12px; }
    .pump-item { border: 1px solid var(--border); border-radius: 12px; padding: 14px; display: grid; gap: 8px; }
    .pump-item strong { font-size: 17px; }
    .meta { color: var(--muted); font-size: 13px; word-break: break-all; }
    .actions { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 10px; }
    pre { margin: 0; overflow: auto; background: #101828; color: #d0d5dd; padding: 16px; border-radius: 12px; min-height: 160px; }
    .status { color: var(--muted); }
    .status.ok { color: var(--ok); }
    .status.err { color: var(--danger); }
    @media (max-width: 860px) {
      .grid, .form-grid, .actions { grid-template-columns: 1fr; }
      header, main { padding-left: 16px; padding-right: 16px; }
    }
  </style>
</head>
<body>
  <header>
    <h1>顶峰永磁高速深井泵 IoT 管理控制台</h1>
    <p>通过腾讯云 IoT Explorer 物模型接口登记设备、读取设备数据，并下发启动、停止、频率设定、模式切换和故障复位命令。</p>
  </header>

  <main>
    <section class="grid">
      <div class="card">
        <h2>设备档案</h2>
        <form id="pump-form" class="form-grid">
          <label>泵 ID
            <input name="id" placeholder="pump-001">
          </label>
          <label>名称
            <input name="name" value="顶峰永磁高速深井泵">
          </label>
          <label>ProductId
            <input name="product_id" required placeholder="腾讯云 IoT Explorer 产品 ID">
          </label>
          <label>DeviceName
            <input name="device_name" required placeholder="设备名称">
          </label>
          <label>安装位置
            <input name="location" placeholder="1# 深井泵房">
          </label>
          <label>最高频率 Hz
            <input name="max_frequency_hz" type="number" min="1" value="400">
          </label>
          <label class="full">备注
            <input name="notes" placeholder="井深、功率、水位传感器等信息">
          </label>
          <button type="submit">保存设备</button>
          <button type="button" class="secondary" id="refresh">刷新列表</button>
        </form>
      </div>

      <div class="card">
        <h2>控制命令</h2>
        <div class="form-grid">
          <label class="full">API Token（如已配置 APP_API_TOKEN）
            <input id="api-token" type="password" placeholder="保存在当前浏览器 localStorage">
          </label>
          <label class="full">选择设备
            <select id="pump-select"></select>
          </label>
          <button id="start">启动</button>
          <button id="stop" class="danger">停止</button>
          <label>目标频率 Hz
            <input id="frequency" type="number" min="0" value="50">
          </label>
          <button id="set-frequency">设定频率</button>
          <label>运行模式
            <input id="mode" placeholder="auto/manual">
          </label>
          <button id="set-mode">切换模式</button>
          <button id="reset-fault" class="secondary">故障复位</button>
          <button id="read-data" class="secondary">读取设备数据</button>
        </div>
      </div>
    </section>

    <section class="grid">
      <div class="card">
        <h2>设备列表</h2>
        <div id="pump-list" class="pump-list"></div>
      </div>
      <div class="card">
        <h2>接口响应</h2>
        <p id="status" class="status">等待操作。</p>
        <pre id="output">{}</pre>
      </div>
    </section>
  </main>

  <script>
    const pumpForm = document.querySelector('#pump-form');
    const pumpList = document.querySelector('#pump-list');
    const pumpSelect = document.querySelector('#pump-select');
    const output = document.querySelector('#output');
    const statusLine = document.querySelector('#status');
    const apiToken = document.querySelector('#api-token');

    let pumps = [];
    apiToken.value = localStorage.getItem('pump_api_token') || '';
    apiToken.addEventListener('input', () => localStorage.setItem('pump_api_token', apiToken.value));

    function showStatus(message, ok = true) {
      statusLine.textContent = message;
      statusLine.className = 'status ' + (ok ? 'ok' : 'err');
    }

    function showOutput(data) {
      output.textContent = JSON.stringify(data, null, 2);
    }

    function escapeHtml(value) {
      return String(value ?? '').replace(/[&<>"']/g, (char) => ({
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;',
      }[char]));
    }

    async function api(path, options = {}) {
      const headers = { 'Content-Type': 'application/json' };
      if (apiToken.value) {
        headers['X-Api-Token'] = apiToken.value;
      }

      const response = await fetch(path, {
        headers,
        ...options,
      });
      const data = await response.json();
      showOutput(data);
      if (!response.ok) {
        throw new Error(data.error?.message || '请求失败');
      }
      return data;
    }

    async function loadPumps() {
      try {
        const data = await api('/api/pumps');
        pumps = data.data || [];
        renderPumps();
        showStatus('设备列表已刷新。');
      } catch (error) {
        showStatus(error.message, false);
      }
    }

    function renderPumps() {
      pumpSelect.innerHTML = pumps.map((pump) => `<option value="${escapeHtml(pump.id)}">${escapeHtml(pump.name)} (${escapeHtml(pump.id)})</option>`).join('');
      pumpList.innerHTML = pumps.length ? pumps.map((pump) => `
        <article class="pump-item">
          <strong>${escapeHtml(pump.name)}</strong>
          <div class="meta">ID: ${escapeHtml(pump.id)}</div>
          <div class="meta">ProductId: ${escapeHtml(pump.product_id)} / DeviceName: ${escapeHtml(pump.device_name)}</div>
          <div class="meta">位置: ${escapeHtml(pump.location || '-')} / 最高频率: ${escapeHtml(pump.max_frequency_hz)} Hz</div>
          <div class="actions">
            <button type="button" class="secondary" data-edit="${escapeHtml(pump.id)}">编辑</button>
            <button type="button" class="danger" data-delete="${escapeHtml(pump.id)}">删除</button>
          </div>
        </article>
      `).join('') : '<p class="meta">暂无设备，请先登记 ProductId 和 DeviceName。</p>';
    }

    function selectedPumpId() {
      if (!pumpSelect.value) {
        throw new Error('请先选择设备。');
      }
      return pumpSelect.value;
    }

    async function sendCommand(command, extra = {}) {
      try {
        const id = selectedPumpId();
        const data = await api(`/api/pumps/${encodeURIComponent(id)}/control`, {
          method: 'POST',
          body: JSON.stringify({ command, ...extra }),
        });
        showStatus(`命令 ${command} 已下发。`);
        return data;
      } catch (error) {
        showStatus(error.message, false);
      }
    }

    pumpForm.addEventListener('submit', async (event) => {
      event.preventDefault();
      const payload = Object.fromEntries(new FormData(pumpForm).entries());
      payload.max_frequency_hz = Number(payload.max_frequency_hz || 400);
      try {
        const exists = pumps.some((pump) => pump.id === payload.id);
        const path = exists ? `/api/pumps/${encodeURIComponent(payload.id)}` : '/api/pumps';
        const method = exists ? 'PUT' : 'POST';
        await api(path, { method, body: JSON.stringify(payload) });
        pumpForm.reset();
        pumpForm.elements.name.value = '顶峰永磁高速深井泵';
        pumpForm.elements.max_frequency_hz.value = 400;
        await loadPumps();
        showStatus('设备已保存。');
      } catch (error) {
        showStatus(error.message, false);
      }
    });

    pumpList.addEventListener('click', async (event) => {
      const editId = event.target.dataset?.edit;
      const deleteId = event.target.dataset?.delete;
      if (editId) {
        const pump = pumps.find((item) => item.id === editId);
        if (pump) {
          for (const [key, value] of Object.entries(pump)) {
            if (pumpForm.elements[key]) {
              pumpForm.elements[key].value = value;
            }
          }
        }
      }
      if (deleteId && confirm('确认删除该设备档案？')) {
        try {
          await api(`/api/pumps/${encodeURIComponent(deleteId)}`, { method: 'DELETE' });
          await loadPumps();
          showStatus('设备已删除。');
        } catch (error) {
          showStatus(error.message, false);
        }
      }
    });

    document.querySelector('#refresh').addEventListener('click', loadPumps);
    document.querySelector('#start').addEventListener('click', () => sendCommand('start'));
    document.querySelector('#stop').addEventListener('click', () => sendCommand('stop'));
    document.querySelector('#set-frequency').addEventListener('click', () => sendCommand('set_frequency', {
      frequency_hz: Number(document.querySelector('#frequency').value),
    }));
    document.querySelector('#set-mode').addEventListener('click', () => sendCommand('set_mode', {
      mode: document.querySelector('#mode').value,
    }));
    document.querySelector('#reset-fault').addEventListener('click', () => sendCommand('reset_fault'));
    document.querySelector('#read-data').addEventListener('click', async () => {
      try {
        const id = selectedPumpId();
        await api(`/api/pumps/${encodeURIComponent(id)}/data`);
        showStatus('设备数据读取完成。');
      } catch (error) {
        showStatus(error.message, false);
      }
    });

    loadPumps();
  </script>
</body>
</html>
