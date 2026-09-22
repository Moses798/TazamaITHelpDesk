    </div>
  </div>
</div>

<?php if (!empty($unseen_bubbles)): ?>
<div class="float-bubbles">
  <?php foreach ($unseen_bubbles as $b):
        $t = $b['ticket'];
        $ac = td_avatar_colors($b['last_from']);
        $subj = strlen($t['subject']) > 34 ? substr($t['subject'], 0, 34) . '…' : $t['subject'];
  ?>
    <div style="position:relative;">
      <a class="float-bubble" href="ticket.php?id=<?= (int) $t['id'] ?>">
        <div style="position:relative;flex-shrink:0;">
          <div class="avatar" style="width:36px;height:36px;font-size:13px;background:<?= $ac[0] ?>;color:<?= $ac[1] ?>;"><?= h(td_initials($b['last_from'])) ?></div>
          <span class="pulse-dot"></span>
        </div>
        <div style="flex:1;min-width:0;">
          <div style="display:flex;align-items:center;gap:6px;">
            <span style="font-size:12.5px;font-weight:700;color:var(--text-dark);"><?= h($b['last_from']) ?></span>
            <?php if ($b['count'] > 1): ?>
              <span class="badge" style="background:var(--brand-soft);color:var(--brand-dark);font-size:10px;"><?= (int) $b['count'] ?> new</span>
            <?php endif; ?>
          </div>
          <div class="mono" style="font-size:10.5px;color:var(--text-faint);margin-top:1px;">#<?= (int) $t['id'] ?> · <?= h($subj) ?></div>
          <div style="font-size:12px;color:var(--text-mid);margin-top:5px;line-height:1.4;max-height:2.8em;overflow:hidden;"><?= h($b['last_text']) ?></div>
        </div>
      </a>
      <a class="float-bubble-dismiss" href="dismiss_bubble.php?id=<?= (int) $t['id'] ?>" title="Dismiss">&times;</a>
    </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- Tazai Chat Widget -->
<div id="tazai-widget" style="position:fixed;bottom:24px;right:24px;z-index:9999;display:flex;flex-direction:column;align-items:flex-end;gap:10px;">

  <!-- Chat panel -->
  <div id="tazai-panel" style="display:none;width:320px;background:#fff;border:1px solid #e4e3e0;border-radius:16px;box-shadow:0 8px 32px rgba(0,0,0,0.15);overflow:hidden;font-family:inherit;">

    <!-- Header -->
    <div style="background:#0B0B0F;padding:14px 16px;display:flex;align-items:center;gap:10px;">
      <div style="width:36px;height:36px;background:#1E4DFF;border-radius:50%;display:flex;align-items:center;justify-content:center;font-weight:700;color:#fff;font-size:15px;flex-shrink:0;">T</div>
      <div style="flex:1;">
        <div style="color:#fff;font-weight:600;font-size:14px;line-height:1.2;">Tazai IT Support</div>
        <div style="color:rgba(255,255,255,.5);font-size:11px;">Ask me anything — I'll help or log a ticket</div>
      </div>
      <button onclick="document.getElementById('tazai-panel').style.display='none'" style="background:none;border:none;color:rgba(255,255,255,.5);cursor:pointer;font-size:20px;line-height:1;padding:0;">&times;</button>
    </div>

    <!-- Messages area -->
    <div id="tazai-messages" style="padding:14px 16px;max-height:280px;overflow-y:auto;display:flex;flex-direction:column;gap:10px;">
      <div class="tazai-msg tazai-msg-agent">
        Hi! I'm Tazai, your IT support assistant. What can I help you with today?
      </div>
    </div>

    <!-- Input area -->
    <div style="padding:10px 12px;border-top:1px solid #f0efed;display:flex;gap:8px;align-items:flex-end;">
      <textarea id="tazai-input" placeholder="Describe your issue..." rows="2" style="flex:1;border:1px solid #e4e3e0;border-radius:8px;padding:8px 10px;font-size:13px;font-family:inherit;resize:none;outline:none;color:#2B2622;line-height:1.4;" onkeydown="if(event.key==='Enter'&&!event.shiftKey){event.preventDefault();tazaiSend();}"></textarea>
      <button onclick="tazaiSend()" style="background:#1E4DFF;border:none;border-radius:8px;padding:9px 14px;color:#fff;cursor:pointer;font-size:13px;font-weight:600;flex-shrink:0;height:38px;">Send</button>
    </div>

    <!-- Channel choice (shown after first message) -->
    <div id="tazai-channel-choice" style="display:none;padding:10px 14px 14px;border-top:1px solid #f0efed;text-align:center;">
      <div style="font-size:12px;color:#888;margin-bottom:8px;">Continue on:</div>
      <div style="display:flex;gap:8px;justify-content:center;">
        <button onclick="tazaiContinueHere()" style="flex:1;border:1px solid #e4e3e0;background:#fff;border-radius:8px;padding:8px 12px;font-size:12.5px;cursor:pointer;color:#2B2622;font-weight:500;">
          💬 This website
        </button>
        <a id="tazai-wa-link" href="#" target="_blank" rel="noopener" style="flex:1;background:#25D366;border:none;border-radius:8px;padding:8px 12px;font-size:12.5px;cursor:pointer;color:#fff;font-weight:500;text-decoration:none;display:flex;align-items:center;justify-content:center;gap:5px;">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="#fff"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>
          WhatsApp
        </a>
      </div>
    </div>
  </div>

  <!-- Floating trigger button -->
  <button id="tazai-fab" onclick="tazaiToggle()" style="display:flex;align-items:center;gap:9px;background:#0B0B0F;color:#fff;border:none;border-radius:50px;padding:12px 20px 12px 16px;box-shadow:0 4px 20px rgba(0,0,0,0.25);font-size:14px;font-weight:600;cursor:pointer;transition:transform .15s,box-shadow .2s;" onmouseover="this.style.transform='translateY(-2px)';this.style.boxShadow='0 8px 28px rgba(0,0,0,0.35)'" onmouseout="this.style.transform='translateY(0)';this.style.boxShadow='0 4px 20px rgba(0,0,0,0.25)'">
    <div style="width:28px;height:28px;background:#1E4DFF;border-radius:50%;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:13px;flex-shrink:0;">T</div>
    Ask Tazai
    <span id="tazai-unread" style="display:none;background:#E53E3E;color:#fff;border-radius:50%;width:18px;height:18px;font-size:11px;display:flex;align-items:center;justify-content:center;font-weight:700;">1</span>
  </button>
</div>

<script src="assets/priority.js"></script>

<style>
.tazai-msg { font-size:13px; line-height:1.45; max-width:85%; padding:9px 12px; border-radius:12px; word-break:break-word; }
.tazai-msg-agent { background:#f4f3f1; color:#2B2622; align-self:flex-start; border-bottom-left-radius:4px; }
.tazai-msg-user  { background:#1E4DFF; color:#fff; align-self:flex-end; border-bottom-right-radius:4px; }
.tazai-msg-thinking { background:#f4f3f1; color:#999; align-self:flex-start; font-style:italic; }
#tazai-messages::-webkit-scrollbar { width:4px; }
#tazai-messages::-webkit-scrollbar-track { background:transparent; }
#tazai-messages::-webkit-scrollbar-thumb { background:#ddd; border-radius:2px; }
</style>

<script>
// Configuration and transient state shared by the floating chat widget.
var TAZAI_API = 'http://<?= $_SERVER['HTTP_HOST'] ? explode(':', $_SERVER['HTTP_HOST'])[0] : '187.7.22.237' ?>:8099/api';
var TAZAI_TOKEN = '<?= defined("TAZAI_API_TOKEN") ? TAZAI_API_TOKEN : "" ?>';
var tazaiHistory = [];
var tazaiFirstMsg = true;

// Toggle the widget panel and clear its unread notification.
function tazaiToggle() {
  var p = document.getElementById('tazai-panel');
  var unread = document.getElementById('tazai-unread');
  p.style.display = p.style.display === 'none' ? 'flex' : 'none';
  p.style.flexDirection = 'column';
  if (unread) unread.style.display = 'none';
  if (p.style.display !== 'none') {
    document.getElementById('tazai-input').focus();
    tazaiScrollBottom();
  }
}

// Keep the most recent chat message visible.
function tazaiScrollBottom() {
  var m = document.getElementById('tazai-messages');
  m.scrollTop = m.scrollHeight;
}

// Append a safely rendered message bubble for a user, agent, or loading state.
function tazaiAddMsg(text, role) {
  var m = document.getElementById('tazai-messages');
  var div = document.createElement('div');
  div.className = 'tazai-msg tazai-msg-' + role;
  div.textContent = text;
  m.appendChild(div);
  tazaiScrollBottom();
  return div;
}

// Submit the user's message and update the conversation with the API result.
function tazaiSend() {
  var input = document.getElementById('tazai-input');
  var text = input.value.trim();
  if (!text) return;
  input.value = '';

  tazaiAddMsg(text, 'user');
  tazaiHistory.push({role:'user', content:text});

  // Show thinking
  var thinking = tazaiAddMsg('Tazai is thinking...', 'thinking');

  // Call Tazai API chat endpoint
  fetch(TAZAI_API + '/chat', {
    method: 'POST',
    headers: {'Content-Type':'application/json', 'X-Tazai-Token': TAZAI_TOKEN},
    body: JSON.stringify({message: text, history: tazaiHistory})
  })
  .then(function(r){ return r.json(); })
  .then(function(d) {
    thinking.remove();

    // Handle known error states — show specific message, no spinner implied
    if (d.error === 'unavailable') {
      tazaiAddMsg('Tazai is currently unavailable while the system is being developed.', 'agent');
      return;
    }
    if (d.error === 'unauthorized') {
      tazaiAddMsg('Tazai is available only to authorized Tazama employees.', 'agent');
      return;
    }

    var reply = d.reply || 'Sorry, I had trouble responding. Please try again.';
    tazaiAddMsg(reply, 'agent');
    tazaiHistory.push({role:'assistant', content:reply});

    // Show channel choice after first successful exchange
    if (tazaiFirstMsg) {
      tazaiFirstMsg = false;
      var waLink = 'https://wa.me/260963796239?text=' + encodeURIComponent('Hi Tazai, I need IT support. ' + text);
      document.getElementById('tazai-wa-link').href = waLink;
      setTimeout(function(){
        document.getElementById('tazai-channel-choice').style.display = 'block';
        tazaiScrollBottom();
      }, 800);
    }
  })
  .catch(function() {
    thinking.remove();
    tazaiAddMsg('I\'m having connection issues. You can still reach me on WhatsApp.', 'agent');
    // Show WhatsApp fallback
    var waLink = 'https://wa.me/260963796239?text=' + encodeURIComponent('Hi Tazai, I need IT support.');
    document.getElementById('tazai-wa-link').href = waLink;
    document.getElementById('tazai-channel-choice').style.display = 'block';
    tazaiScrollBottom();
  });
}

// Hide the channel chooser when the user stays in the web chat.
function tazaiContinueHere() {
  document.getElementById('tazai-channel-choice').style.display = 'none';
  document.getElementById('tazai-input').focus();
}

// Show unread indicator after 3s on first page load
setTimeout(function(){
  if (document.getElementById('tazai-panel').style.display === 'none') {
    var u = document.getElementById('tazai-unread');
    if (u) { u.style.display = 'flex'; }
  }
}, 3000);
</script>

</body>
</html>
