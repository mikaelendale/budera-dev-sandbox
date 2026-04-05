'use client';

import { useCallback, useEffect, useState } from 'react';

type State = {
  fail_next_ach: boolean;
  fail_next_wire: boolean;
  fail_next_check_clear: boolean;
  accounts: {
    id: string;
    currency: string;
    balance_cents: number;
    created_at: string;
  }[];
  transfers: {
    transfer_id: string;
    rail: string;
    amount_cents: number;
    status: string;
    account_id: string | null;
    from_account_id: string | null;
    to_account_id: string | null;
    created_at: string;
  }[];
  kyc_submissions: {
    id: string;
    status: string;
    account_id: string | null;
    created_at: string;
  }[];
};

export default function ControlPage() {
  const [state, setState] = useState<State | null>(null);
  const [refInput, setRefInput] = useState('');
  const [err, setErr] = useState<string | null>(null);

  const refresh = useCallback(async () => {
    setErr(null);
    const res = await fetch('/api/control/state');
    if (!res.ok) {
      setErr('Failed to load state');
      return;
    }
    setState(await res.json());
  }, []);

  useEffect(() => {
    const id = window.setInterval(() => void refresh(), 2000);
    void refresh();
    return () => window.clearInterval(id);
  }, [refresh]);

  async function toggleFail(target: 'ach' | 'wire' | 'check_clear') {
    setErr(null);
    const key =
      target === 'ach'
        ? 'fail_next_ach'
        : target === 'wire'
          ? 'fail_next_wire'
          : 'fail_next_check_clear';
    const cur = state?.[key];
    const res = await fetch('/api/control/fail-next', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ enabled: !cur, target }),
    });
    if (!res.ok) {
      setErr('Failed to toggle');
      return;
    }
    void refresh();
  }

  async function settleNow() {
    setErr(null);
    const res = await fetch('/api/control/settle-now', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ ref: refInput.trim() }),
    });
    if (!res.ok) {
      const j = await res.json().catch(() => ({}));
      setErr((j as { error?: string }).error ?? 'Settle failed');
      return;
    }
    setRefInput('');
    void refresh();
  }

  return (
    <main style={{ padding: '2rem', maxWidth: '56rem' }}>
      <h1>Mock bank control</h1>
      <p style={{ color: '#444' }}>
        Sandbox: accounts, transfers by rail, KYC submissions, fail-next ACH, wire, check clear, and
        manual ACH settle (forces success).
      </p>
      {err && <p style={{ color: 'crimson' }}>{err}</p>}
      <section style={{ marginBottom: '2rem' }}>
        <h2>Fail next</h2>
        <p>
          ACH: next settlement fails (<code>transfer.ach.failed</code>). Wire: next wire fails
          after send. Check: next clear becomes a return.
        </p>
        <button type="button" onClick={() => void toggleFail('ach')} style={{ marginRight: 8 }}>
          {state?.fail_next_ach ? 'Disable' : 'Enable'} fail-next ACH
        </button>
        <button type="button" onClick={() => void toggleFail('wire')} style={{ marginRight: 8 }}>
          {state?.fail_next_wire ? 'Disable' : 'Enable'} fail-next wire
        </button>
        <button type="button" onClick={() => void toggleFail('check_clear')}>
          {state?.fail_next_check_clear ? 'Disable' : 'Enable'} fail-next check clear
        </button>
      </section>
      <section style={{ marginBottom: '2rem' }}>
        <h2>Settle ACH now</h2>
        <p>Pending ACH transfer id (<code>trf_...</code>).</p>
        <input
          value={refInput}
          onChange={(e) => setRefInput(e.target.value)}
          placeholder="trf_..."
          style={{ width: '100%', maxWidth: '28rem', padding: '0.5rem' }}
        />
        <button type="button" style={{ marginLeft: '0.5rem' }} onClick={() => void settleNow()}>
          Settle now
        </button>
      </section>
      <section>
        <h2>Accounts</h2>
        {!state ? (
          <p>Loading…</p>
        ) : (
          <table cellPadding={8} style={{ borderCollapse: 'collapse', width: '100%' }}>
            <thead>
              <tr style={{ textAlign: 'left', borderBottom: '1px solid #ccc' }}>
                <th>ID</th>
                <th>Balance (cents)</th>
                <th>Currency</th>
              </tr>
            </thead>
            <tbody>
              {state.accounts.map((a) => (
                <tr key={a.id}>
                  <td>
                    <code>{a.id}</code>
                  </td>
                  <td>{a.balance_cents}</td>
                  <td>{a.currency}</td>
                </tr>
              ))}
            </tbody>
          </table>
        )}
      </section>
      <section style={{ marginTop: '2rem' }}>
        <h2>Transfers</h2>
        {!state ? null : (
          <table cellPadding={8} style={{ borderCollapse: 'collapse', width: '100%' }}>
            <thead>
              <tr style={{ textAlign: 'left', borderBottom: '1px solid #ccc' }}>
                <th>ID</th>
                <th>Rail</th>
                <th>Amount</th>
                <th>Status</th>
              </tr>
            </thead>
            <tbody>
              {state.transfers.map((t) => (
                <tr key={t.transfer_id}>
                  <td>
                    <code>{t.transfer_id}</code>
                  </td>
                  <td>{t.rail}</td>
                  <td>{t.amount_cents}</td>
                  <td>{t.status}</td>
                </tr>
              ))}
            </tbody>
          </table>
        )}
      </section>
      <section style={{ marginTop: '2rem' }}>
        <h2>KYC submissions</h2>
        {!state ? null : (
          <table cellPadding={8} style={{ borderCollapse: 'collapse', width: '100%' }}>
            <thead>
              <tr style={{ textAlign: 'left', borderBottom: '1px solid #ccc' }}>
                <th>ID</th>
                <th>Status</th>
                <th>Account</th>
              </tr>
            </thead>
            <tbody>
              {state.kyc_submissions.map((k) => (
                <tr key={k.id}>
                  <td>
                    <code>{k.id}</code>
                  </td>
                  <td>{k.status}</td>
                  <td>{k.account_id ?? '—'}</td>
                </tr>
              ))}
            </tbody>
          </table>
        )}
      </section>
    </main>
  );
}
