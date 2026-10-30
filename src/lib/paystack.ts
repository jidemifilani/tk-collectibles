const PAYSTACK_BASE = "https://api.paystack.co";

export type PaystackInitResponse = {
  status: boolean;
  message: string;
  data: {
    authorization_url: string;
    access_code: string;
    reference: string;
  };
};

export type PaystackVerifyResponse = {
  status: boolean;
  message: string;
  data: {
    status: string;
    reference: string;
    amount: number;
    currency: string;
    customer: { email: string };
  };
};

function secretKey(): string {
  const key = process.env.PAYSTACK_SECRET_KEY;
  if (!key) throw new Error("PAYSTACK_SECRET_KEY is not configured");
  return key;
}

export async function initializeTransaction(payload: {
  email: string;
  amount: number;
  reference: string;
  metadata?: Record<string, unknown>;
  callback_url?: string;
}): Promise<PaystackInitResponse> {
  const res = await fetch(`${PAYSTACK_BASE}/transaction/initialize`, {
    method: "POST",
    headers: {
      Authorization: `Bearer ${secretKey()}`,
      "Content-Type": "application/json",
    },
    body: JSON.stringify({
      email: payload.email,
      amount: payload.amount,
      reference: payload.reference,
      currency: "NGN",
      metadata: payload.metadata,
      callback_url: payload.callback_url,
    }),
  });

  const data = (await res.json()) as PaystackInitResponse;
  if (!res.ok || !data.status) {
    throw new Error(data.message || "Failed to initialize Paystack payment");
  }
  return data;
}

export async function verifyTransaction(
  reference: string
): Promise<PaystackVerifyResponse> {
  const res = await fetch(
    `${PAYSTACK_BASE}/transaction/verify/${encodeURIComponent(reference)}`,
    {
      headers: { Authorization: `Bearer ${secretKey()}` },
    }
  );

  const data = (await res.json()) as PaystackVerifyResponse;
  if (!res.ok || !data.status) {
    throw new Error(data.message || "Failed to verify payment");
  }
  return data;
}
