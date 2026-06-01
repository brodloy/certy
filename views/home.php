<?php /** Landing page content (wrapped by the public layout). */ ?>

<!-- ===== Hero (minimal, stacked) ===== -->
<section class="landing-hero">
    <div class="hero-inner">
        <h1 class="reveal">Stop getting <span class="accent">surprised</span> by expired certificates.</h1>
        <p class="lead reveal d1">certy monitors your SSL certificates and domains, and warns you in time.</p>
        <div class="hero-cta reveal d2">
            <?php if (config('demo_enabled', true)): ?>
            <form method="post" action="<?= e(url('/demo')) ?>" class="d-inline m-0">
                <?= csrf_field() ?>
                <button class="btn btn-primary" type="submit">Try the live demo</button>
            </form>
            <?php endif; ?>
            <a class="btn btn-outline-secondary" href="<?= e(url('/register')) ?>">Create an account</a>
        </div>
        <a class="hero-secondary reveal d2" href="#how">See how it works &rarr;</a>
    </div>

    <div class="hero-mock reveal d2">
        <div class="browser-frame">
            <div class="browser-bar">
                <span class="browser-dot"></span><span class="browser-dot"></span><span class="browser-dot"></span>
                <span class="browser-url">certy/dashboard</span>
            </div>
            <div class="mini-dash">
                <div class="mini-title">Your monitors</div>
                <div class="mini-row">
                    <span class="mini-host">api.acme.io</span>
                    <span class="mini-days" style="color:var(--ok)">75 days</span>
                    <span class="badge-soft is-healthy">healthy</span>
                </div>
                <div class="mini-row">
                    <span class="mini-host">acme.io</span>
                    <span class="mini-days" style="color:var(--warn)">21 days</span>
                    <span class="badge-soft is-warning">warning</span>
                </div>
                <div class="mini-row is-crit">
                    <span class="mini-host">mail.acme.io</span>
                    <span class="mini-days" style="color:var(--danger)">5 days</span>
                    <span class="badge-soft is-critical">critical</span>
                </div>
                <div class="mini-row">
                    <span class="mini-host">acme-labs.org</span>
                    <span class="mini-days" style="color:var(--ok)">155 days</span>
                    <span class="badge-soft is-healthy">healthy</span>
                </div>
            </div>
        </div>
    </div>

    <div class="hero-ticks reveal d3">
        <span>Nothing to install</span>
        <span>Monitored from the outside, 24/7</span>
        <span>Alerts before anything lapses</span>
    </div>
</section>

<!-- ===== Trust strip ===== -->
<section class="trust-strip">
    <div class="container">
        <div class="trust-label">Works with any TLS host &amp; registrar</div>
        <div class="trust-chips">
            <span class="trust-chip">Let's Encrypt</span>
            <span class="trust-chip">DigiCert</span>
            <span class="trust-chip">ZeroSSL</span>
            <span class="trust-chip">Sectigo</span>
            <span class="trust-chip">Internal CAs</span>
            <span class="trust-chip">.com / .org / .io</span>
            <span class="trust-chip">.co.uk</span>
        </div>
    </div>
</section>

<!-- ===== How it works ===== -->
<section class="section" id="how">
    <div class="container">
        <div class="section-head">
            <h2>Set it up once. Stop worrying.</h2>
            <p>Three steps to never tracking a renewal date in a spreadsheet again.</p>
        </div>
        <div class="steps">
            <div class="step">
                <div class="step-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                </div>
                <h3><span class="num">01</span>Add what to watch</h3>
                <p>Drop in the hosts and domains you care about &mdash; up to ten. Each list is private to your account.</p>
            </div>
            <div class="step">
                <div class="step-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2"/><path d="M3 9h18M9 21V9"/></svg>
                </div>
                <h3><span class="num">02</span>See it at a glance</h3>
                <p>A colour-coded dashboard shows days-left for every target: green when healthy, amber as it nears, red when it's urgent.</p>
            </div>
            <div class="step">
                <div class="step-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.7 21a2 2 0 0 1-3.4 0"/></svg>
                </div>
                <h3><span class="num">03</span>Get warned in time</h3>
                <p>A daily check runs on its own and emails you at 30, 14, 7 and 1 days out &mdash; once each, never nagging.</p>
            </div>
        </div>
    </div>
</section>

<!-- ===== Features ===== -->
<section class="section" style="padding-top:0;">
    <div class="container">
        <div class="feature-grid">
            <div class="card feature-card"><div class="card-body">
                <div class="feat-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                </div>
                <h3>Real certificate checks</h3>
                <p class="text-muted-2 mb-0">A direct TLS handshake reads each host's certificate and its true expiry &mdash; the same thing a browser sees. Nothing to install on your side; we check every host from the outside.</p>
            </div></div>
            <div class="card feature-card"><div class="card-body">
                <div class="feat-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M12 8v4l3 2"/></svg>
                </div>
                <h3>Domain expiry too</h3>
                <p class="text-muted-2 mb-0">A raw WHOIS lookup tracks when each domain registration lapses, so a missed renewal can't quietly drop your name from under you.</p>
            </div></div>
            <div class="card feature-card"><div class="card-body">
                <div class="feat-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.7 21a2 2 0 0 1-3.4 0"/></svg>
                </div>
                <h3>Tiered alerts, no spam</h3>
                <p class="text-muted-2 mb-0">Warnings fire at 30, 14, 7 and 1 days out &mdash; each sent exactly once per expiry. Renew, and the countdown quietly resets.</p>
            </div></div>
            <div class="card feature-card"><div class="card-body">
                <div class="feat-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="4 17 10 11 4 5"/><line x1="12" y1="19" x2="20" y2="19"/></svg>
                </div>
                <h3>Check on demand</h3>
                <p class="text-muted-2 mb-0">Don't want to wait for the nightly run? Hit <em>Check now</em> on any target and watch the status refresh live.</p>
            </div></div>
        </div>
    </div>
</section>

<!-- ===== Terminal flex ===== -->
<section class="section" style="padding-top:0;">
    <div class="container">
        <div class="section-head">
            <h2>It's just a handshake and a lookup.</h2>
            <p>No magic, no black box &mdash; the same low-level checks you'd run by hand, watched for you around the clock.</p>
        </div>
        <div class="terminal">
            <div class="term-bar">
                <span class="browser-dot"></span><span class="browser-dot"></span><span class="browser-dot"></span>
                <span class="term-name">certy &mdash; nightly check</span>
            </div>
            <div class="term-body">
                <div><span class="prompt">$</span> <span class="cmd">certy check mail.acme.io</span></div>
                <div><span class="muted">→ opening TLS socket … connected</span></div>
                <div><span class="key">issuer</span><span class="val">Let's Encrypt</span></div>
                <div><span class="key">expires</span><span class="val">2026-06-05</span> <span class="warn">(5 days)</span></div>
                <div><span class="key">status</span><span class="warn">⚠ critical — alert sent</span></div>
                <div>&nbsp;</div>
                <div><span class="prompt">$</span> <span class="cmd">certy check acme.io --whois</span></div>
                <div><span class="key">registrar</span><span class="val">Gandi SAS</span></div>
                <div><span class="key">expires</span><span class="val">2026-06-21</span> <span class="ok">(21 days)</span></div>
                <div><span class="key">status</span><span class="ok">✓ healthy</span></div>
            </div>
        </div>
    </div>
</section>

<!-- ===== Closing CTA ===== -->
<section class="section" style="padding-top:0;">
    <div class="container">
        <div class="cta-band">
            <h2>See it working in one click.</h2>
            <p>Jump into a live, pre-loaded demo &mdash; or create your own account to watch your hosts.</p>
            <?php if (config('demo_enabled', true)): ?>
            <form method="post" action="<?= e(url('/demo')) ?>" class="d-inline m-0">
                <?= csrf_field() ?>
                <button class="btn btn-light" type="submit">Try the live demo</button>
            </form>
            <?php endif; ?>
            <a class="btn btn-outline-light" href="<?= e(url('/register')) ?>" style="margin-left:.5rem;">Create an account</a>
        </div>
    </div>
</section>
