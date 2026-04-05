import crypto from 'crypto';

export type WebhookPayload = {
  event: string;
  id: string;
  occurred_at: string;
  data: Record<string, unknown>;
};

export async function sendBuderaWebhook(payload: WebhookPayload): Promise<void> {
  const url = process.env.BUDERA_WEBHOOK_URL;
  const secret = process.env.BUDERA_WEBHOOK_SECRET;
  if (!url || !secret) {
    return;
  }
  const body = JSON.stringify(payload);
  const signature = crypto.createHmac('sha256', secret).update(body).digest('hex');
  try {
    await fetch(url, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-Signature': `sha256=${signature}`,
      },
      body,
    });
  } catch {
    // Demo: swallow network errors
  }
}
