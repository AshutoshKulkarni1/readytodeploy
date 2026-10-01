      </div> <!-- closes dashboard-content-inner / main -->
    </main> <!-- closes dashboard-content / page -->
  </div> <!-- closes dashboard-main-col -->
</div> <!-- closes dashboard-layout / app -->

<!-- Floating study assistant -->
<button class="fab" id="chatFab" aria-expanded="false" aria-controls="chat" aria-label="Open study assistant" type="button">
  <svg class="ic i-bot"><use href="#i-bot"/></svg><svg class="ic i-close"><use href="#i-x"/></svg>
</button>
<section class="chat" id="chat" aria-label="Study assistant">
  <div class="chat__head">
    <span class="avatar"><svg class="ic"><use href="#i-bot"/></svg></span>
    <div><b>Study assistant</b><small>Answers use your test data</small></div>
  </div>
  <div class="chat__log" id="chatLog" role="log" aria-live="polite">
    <div class="msg msg--bot">Hi <?= h($firstName ?? 'there') ?>. Ask me about your results, or pick one of the shortcuts below.</div>
  </div>
  <div class="pills" id="pills">
    <button class="pill" type="button">My weaknesses</button>
    <button class="pill" type="button">What is my PCI?</button>
    <button class="pill" type="button">What should I study?</button>
    <button class="pill" type="button">Generate my report</button>
  </div>
  <div class="chat__in">
    <input id="chatInput" type="text" placeholder="Ask a question" aria-label="Message the study assistant" autocomplete="off">
    <button class="btn btn--primary" id="chatSend" aria-label="Send message" type="button"><svg class="ic"><use href="#i-send"/></svg></button>
  </div>
</section>

<!-- Theme, Mobile Sidebar, and AI Assistant Scripts -->
<script>
(function () {
  'use strict';
  function setTheme(t) {
    document.documentElement.setAttribute('data-theme', t);
    var ds = document.getElementById('darkSwitch');
    if (ds) ds.setAttribute('aria-checked', String(t === 'dark'));
    try { localStorage.setItem('lms-theme', t); } catch (e) {}
  }
  function curTheme() {
    return document.documentElement.getAttribute('data-theme') || 'light';
  }
  window.toggleTheme = function() {
    setTheme(curTheme() === 'dark' ? 'light' : 'dark');
  };
  var ds = document.getElementById('darkSwitch');
  if (ds) {
    ds.setAttribute('aria-checked', String(curTheme() === 'dark'));
    ds.addEventListener('click', window.toggleTheme);
  }
  var tb = document.getElementById('themeBtn');
  if (tb) tb.addEventListener('click', window.toggleTheme);

  var sb = document.getElementById('sidebar');
  var sc = document.getElementById('scrim');
  var mb = document.getElementById('menuBtn');
  window.toggleSidebar = function() {
    if (!sb) return;
    var open = !sb.classList.contains('open');
    sb.classList.toggle('open', open);
    if (sc) sc.classList.toggle('show', open);
    document.body.style.overflow = open ? 'hidden' : '';
    if (mb) mb.setAttribute('aria-label', open ? 'Close navigation' : 'Open navigation');
  };
  if (mb) mb.addEventListener('click', window.toggleSidebar);
  if (sc) sc.addEventListener('click', function() { window.toggleSidebar(); });

  var chat = document.getElementById('chat'), fab = document.getElementById('chatFab'), log = document.getElementById('chatLog'), input = document.getElementById('chatInput'), sendBtn = document.getElementById('chatSend'), pills = document.getElementById('pills');
  var REPLIES = {
    'My weaknesses': 'Based on your evaluated tests, focus on revising topics with lower score accuracy. Start with practice tests in your course.',
    'What is my PCI?': 'Your Performance Competency Index is computed from your completed assessments. Check the Analytics tab for detailed breakdowns.',
    'What should I study?': 'Review upcoming assessments and focus on topics scheduled to close soon.',
    'Generate my report': 'You can download comprehensive performance reports under the Results tab.'
  };
  function addMsg(text, who) {
    if (!log) return;
    var d = document.createElement('div'); d.className = 'msg msg--' + who; d.textContent = text;
    log.appendChild(d); log.scrollTop = log.scrollHeight; return d;
  }
  async function ask(q) {
    if (!q || !q.trim()) return;
    q = q.trim();
    addMsg(q, 'me');
    var t = document.createElement('div'); t.className = 'msg msg--bot typing'; t.innerHTML = '<i></i><i></i><i></i>';
    log.appendChild(t); log.scrollTop = log.scrollHeight;
    try {
      var res = await fetch('<?= BASE_URL ?>/api/chat.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ message: q })
      });
      var data = await res.json();
      t.remove();
      var botReply = '';
      if (data && data.message) botReply = data.message;
      else if (data && data.python_response && data.python_response.data && data.python_response.data.message) botReply = data.python_response.data.message;
      else if (data && data.error) botReply = data.error;
      else botReply = REPLIES[q] || 'I can help with your weak topics, your PCI, a study plan or a report. Try one of the shortcuts above.';
      botReply = botReply.replace(/<think>[\s\S]*?<\/think>/gi, '').trim();
      addMsg(botReply, 'bot');
    } catch (e) {
      t.remove();
      addMsg(REPLIES[q] || 'I can help with your weak topics, your PCI, a study plan or a report. Try one of the shortcuts above.', 'bot');
    }
  }
  function chatOpen(open) {
    if (!chat || !fab) return;
    chat.classList.toggle('open', open);
    fab.setAttribute('aria-expanded', String(open));
    fab.setAttribute('aria-label', open ? 'Close study assistant' : 'Open study assistant');
    if (open && input) setTimeout(function () { input.focus(); }, 220);
  }
  if (fab) fab.addEventListener('click', function () { chatOpen(!chat.classList.contains('open')); });
  if (pills) pills.addEventListener('click', function (e) { var p = e.target.closest('.pill'); if (p) ask(p.textContent); });
  if (sendBtn && input) sendBtn.addEventListener('click', function () { var v = input.value; input.value = ''; ask(v); });
  if (input) input.addEventListener('keydown', function (e) { if (e.key === 'Enter') { var v = input.value; input.value = ''; ask(v); } });
  document.addEventListener('keydown', function (e) {
    if (e.key !== 'Escape') return;
    if (chat && chat.classList.contains('open')) { chatOpen(false); if (fab) fab.focus(); }
  });
})();
</script>
