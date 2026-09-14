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

</body>
</html>
