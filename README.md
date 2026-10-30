# Curated Thrift — Full-Stack Online Store

A modern thrift clothing storefront for vendors who sell through Instagram, TikTok, and WhatsApp. Customers browse curated one-of-one pieces with **actual + discount pricing**, add to cart, and pay securely via **Paystack**.

## Features

- **Dual pricing** — actual price (struck through) + discount sale price on every product
- **Contact page** — standalone `/contact` with clickable customer care phone number
- **Admin panel** — `/admin` to add/edit products, view orders, edit all storefront copy
- **Theme palette** — 7 color pickers in admin apply across the whole site instantly
- **AI customer-care assistant** — floating chat widget that answers product, price, size, delivery & payment enquiries (works with or without an OpenAI key)
- **Paystack checkout** — card, bank transfer, USSD

## Quick start

```bash
npm install
npm run db:push
npm run db:seed
npm run dev
```

- Store: [http://localhost:3000](http://localhost:3000)
- Contact: [http://localhost:3000/contact](http://localhost:3000/contact)
- Admin: [http://localhost:3000/admin](http://localhost:3000/admin)

### Admin login

Set `ADMIN_PASSWORD` in `.env` (default in dev: `thrift-admin-2024`).

### Paystack

Add test keys from [Paystack Dashboard](https://dashboard.paystack.com/#/settings/developer) to `.env`:

```
PAYSTACK_SECRET_KEY=sk_test_...
NEXT_PUBLIC_PAYSTACK_PUBLIC_KEY=pk_test_...
```

## Admin guide

| Section | What you can edit |
|---------|-------------------|
| **Products** | Name, **upload photo from device**, actual/discount price, size, category, stock |
| **Store & Theme** | Store name, hero text, about section, contact phone, email, social links, 7 theme colors |
| **Orders** | View all customer orders and payment status |

## AI assistant

A floating chat widget on the storefront answers customer enquiries (products, prices, sizes, availability, delivery, payment, contact). It reads your live catalog and store settings.

- **No key needed:** built-in rule-based replies handle common questions.
- **Smarter replies:** add `OPENAI_API_KEY` to `.env` for natural conversation (model set by `OPENAI_MODEL`, default `gpt-4o-mini`).

## Environment

| Variable | Purpose |
|----------|---------|
| `ADMIN_PASSWORD` | Admin panel login |
| `OPENAI_API_KEY` | Optional — enables AI chat replies |
| `OPENAI_MODEL` | Optional — AI model (default `gpt-4o-mini`) |
| `PAYSTACK_SECRET_KEY` | Server-side Paystack |
| `NEXT_PUBLIC_PAYSTACK_PUBLIC_KEY` | Client Paystack |
| `NEXT_PUBLIC_APP_URL` | Paystack callback URL |
| `DATABASE_URL` | SQLite path |

Store name, phone, social links, and colors are managed in **Admin → Store & Theme** (not `.env`).

## License

MIT
