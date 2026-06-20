# Thermal Printing — Client Requirements Questionnaire

**Project:** Buyselles (Web + Mobile)  
**Purpose:** Before we configure server-side (VPS) printing, QZ Tray, or mobile Bluetooth printing, we need your answers below.  
**Please fill in every section.** If unsure, write *“Not decided”* and we will advise.

---

## 1. Business context

### 1.1 Who prints receipts?

| Scenario | Needed? (Yes / No / Maybe) | Notes |
|----------|---------------------------|-------|
| **Customer** prints their own digital code receipt after online purchase | | |
| **Shop staff** print receipts at your office/store automatically when orders arrive | | |
| **Shop staff** print manually from admin panel when needed | | |
| **Vendor/seller** prints from vendor panel (POS) | | |
| **Admin** prints from admin panel | | |

### 1.2 Where do sales happen?

- [ ] 100% online (customers buy from home)
- [ ] In-store / kiosk (customer buys at your physical location)
- [ ] Mix of online + in-store
- [ ] Other: _______________________________

### 1.3 Expected volume

- Approx. **digital product orders per day:** ___________
- Peak orders in one hour: ___________
- Do you need **multiple copies** per order? Yes / No — if yes, how many: ___________

---

## 2. Printer hardware (critical)

> **Important:** Server-side printing from your VPS only works with a **network (Ethernet/Wi‑Fi) thermal printer** that your VPS can reach over the internet (usually via VPN). USB-only printers plugged into a PC **cannot** be controlled directly from a remote VPS without extra software on that PC (e.g. QZ Tray).

### 2.1 Do you already have thermal printer(s)?

- [ ] Yes — model(s): _______________________________
- [ ] No — we need to purchase (budget: ___________)
- [ ] Not sure

### 2.2 Printer connection type (check all that apply)

| Type | Have today? | Planned? | Location |
|------|-------------|----------|----------|
| **USB** (cable to PC) | | | |
| **Bluetooth** (phone/tablet) | | | |
| **Wi‑Fi / Ethernet (network, port 9100)** | | | |
| **Cloud printer** (Star CloudPRNT, Epson ePOS, etc.) | | | |

### 2.3 Paper width

- [ ] 80 mm (standard)
- [ ] 58 mm (compact)
- [ ] Both / mixed locations
- [ ] Not sure

### 2.4 Printer brand / model (if known)

| Location | Brand & model | Connection | Serial / MAC (optional) |
|----------|---------------|------------|-------------------------|
| Shop / office | | | |
| Other location 2 | | | |

### 2.5 Language on printed receipt

- [ ] English only
- [ ] Arabic only
- [ ] English + Arabic (bilingual)
- [ ] Other: _______________________________

> **Note:** Arabic on thermal printers often requires image/bitmap printing (mobile app supports this). Confirm if Arabic product names or headers must appear on the **physical receipt**.

---

## 3. Shop / office printing (server-side from VPS)

This is for receipts printed **at your location** when an order is placed — sent from your **live VPS** to a printer on your network.

### 3.1 Do you want automatic printing when a digital order is completed?

- [ ] **Yes** — print immediately when codes are ready (no staff action)
- [ ] **No** — staff click “Print” manually
- [ ] **Both** — auto-print + manual reprint option

### 3.2 Where is the shop printer physically located?

- Address / site name: _______________________________
- Same building as staff PCs? Yes / No
- Is the printer on the **same local network** as your router? Yes / No

### 3.3 Network printer IP (if network printer)

- Printer IP on local network (e.g. `192.168.1.50`): _______________________________
- Does the printer support **raw TCP port 9100** (ESC/POS)? Yes / No / Unknown
- Can you access printer settings in a browser or utility app? Yes / No

### 3.4 VPS ↔ shop connectivity (very important)

Your live website runs on a **VPS (remote server)**. Your shop printer is usually on a **private home/office network**. The VPS **cannot** see `192.168.x.x` unless you connect them.

**Which setup can you provide?**

- [ ] **VPN** (WireGuard / OpenVPN) between VPS and shop router — *recommended*
- [ ] Printer has a **public/static IP** and port 9100 forwarded on router — *security risk; not recommended without firewall*
- [ ] VPS is in the **same office** as the printer (same LAN) — rare for live sites
- [ ] We need **help designing** VPN/network setup
- [ ] We **cannot** do VPN — need alternative (see section 7)

**If VPN:**

- Who will configure it? (Your IT / hosting provider / us): _______________________________
- Router model at shop: _______________________________
- VPS provider (e.g. DigitalOcean, AWS, Hetzner): _______________________________

### 3.5 How many shop locations need server-side printing?

- [ ] One location
- [ ] Multiple locations — list: _______________________________
- [ ] One central printer for all online orders

### 3.6 Receipt content (shop auto-print)

What must appear on the **printed** receipt? (Check all)

- [ ] Shop name & phone
- [ ] Order ID & date/time
- [ ] Customer name
- [ ] Product name(s)
- [ ] **Full digital code** (unmasked)
- [ ] PIN / serial / expiry (if applicable)
- [ ] QR code
- [ ] “Thank you” / footer text
- [ ] Custom text: _______________________________

**Security:** Should the **full code** print at the shop for every online order, or only when staff manually reprint?

- [ ] Auto-print includes full codes
- [ ] Auto-print is order summary only; full codes via manual reprint
- [ ] Not sure — advise us

---

## 4. Customer self-print (after online purchase)

When a customer buys online from home, their printer is **not** on your VPS. Options:

| Method | How it works | Client install needed? |
|--------|--------------|------------------------|
| **A. QZ Tray** | Browser talks to QZ app on customer PC → local printer | Yes — QZ Tray on PC |
| **B. Print preview** | 80 mm styled page → browser Print dialog | No — uses any printer |
| **C. Mobile app** | Bluetooth thermal (already in User app) | Mobile app only |
| **D. Download PDF** | A4 PDF (already available) | No |

### 4.1 Which customer options do you want enabled?

- [ ] **QZ Tray** thermal (best for ESC/POS at customer desk)
- [ ] **Print preview** fallback (no extra software)
- [ ] **Mobile app Bluetooth** only (no web thermal)
- [ ] **A4 PDF** only (no thermal)
- [ ] All of the above

### 4.2 Are your customers typically…

- [ ] Home users on Windows/Mac (can install QZ Tray if instructed)
- [ ] Mobile-only (phone/tablet)
- [ ] Business buyers with their own thermal printers
- [ ] Mix — describe: _______________________________

### 4.3 Will you provide QZ Tray setup instructions to customers?

- [ ] Yes — we will publish a help page / PDF
- [ ] No — preview/PDF is enough
- [ ] Only for B2B / reseller accounts

### 4.4 Should “Thermal Print” on the website…

- [ ] Print on **customer’s** local printer (QZ / preview)
- [ ] Send to **your shop** printer (same as server-side — unusual for home buyers)
- [ ] Let customer **choose** (explain when each applies)

---

## 5. Staff & admin workflows

### 5.1 Admin / vendor panel

- [ ] Reprint digital code receipt from order details page
- [ ] Print from POS module (already exists — separate from digital delivery)
- [ ] Bulk reprint for multiple orders
- [ ] Not needed

### 5.2 Who is allowed to trigger server-side print?

- [ ] System only (automatic)
- [ ] Admin users
- [ ] Vendors (sellers)
- [ ] Customers (via “Thermal Print” button)
- [ ] Specific roles: _______________________________

### 5.3 Audit / logging

- [ ] Log every print job (who, when, order ID)
- [ ] Not required

---

## 6. Mobile app (already built)

The **User app** supports Bluetooth thermal printing with printer scan/pair (similar to POS apps).

### 6.1 Mobile thermal printing

- [ ] Keep as primary method for mobile buyers
- [ ] Deprioritize — web is enough
- [ ] Need changes to receipt layout — describe: _______________________________

### 6.2 Simulation / test mode

- [ ] Staff will test with real Bluetooth printer before go-live
- [ ] Need demo without hardware

---

## 7. Alternatives if VPS cannot reach shop printer

If you **cannot** set up VPN or network printer, choose fallbacks:

| Option | Description | Your preference |
|--------|-------------|-----------------|
| **QZ Tray at shop** | One PC at shop runs QZ Tray; browser prints to USB printer | Yes / No |
| **Local print agent** | Small app on shop PC polls VPS for print jobs | Yes / No |
| **Email/WhatsApp codes only** | No physical print at shop | Yes / No |
| **Manual export** | Staff open order and print from browser | Yes / No |
| **Purchase network printer** | One-time hardware + VPN setup | Yes / No |

---

## 8. Environment & go-live

### 8.1 Environments

| Environment | URL | Need thermal printing? |
|-------------|-----|------------------------|
| Production (live VPS) | | Yes / No |
| Staging | | Yes / No |
| Local dev | | Yes / No |

### 8.2 Live VPS details (for network planning)

- Hosting provider: _______________________________
- Server region: _______________________________
- Can you install **WireGuard** or run VPN client on VPS? Yes / No / Unknown
- Who has SSH/root access? _______________________________

### 8.3 Timeline

- Target go-live date: _______________________________
- Printer hardware ready by: _______________________________
- VPN/network ready by: _______________________________

---

## 9. Security & compliance

### 9.1 Digital codes on paper

- [ ] Accept risk — codes on printed receipts at shop
- [ ] Minimize — only customer receives full code (email/app); shop gets notification only
- [ ] PCI / internal policy — describe: _______________________________

### 9.2 Network exposure

- [ ] We accept VPN-only access to printer (no public port 9100)
- [ ] We require firewall rules — who manages: _______________________________

---

## 10. Summary decision (client to complete)

**Primary goal (pick one main priority):**

1. [ ] **A.** Auto-print every online digital order at our shop (VPS → network printer)  
2. [ ] **B.** Let customers print at home on web (QZ Tray + preview)  
3. [ ] **C.** Mobile Bluetooth printing for customers  
4. [ ] **D.** All of the above with clear rules per scenario  

**One-paragraph description of ideal flow:**

```
(Example: "Customer buys online → gets code in app and email → can tap Thermal Print 
on phone via Bluetooth. At our office, receipt auto-prints on Epson TM-T88 
when payment clears. We use VPN between VPS and shop.")
```

_______________________________________________________________________________  
_______________________________________________________________________________  
_______________________________________________________________________________  

---

## 11. Contacts & approvals

| Role | Name | Email | Phone |
|------|------|-------|-------|
| Business owner / decision maker | | | |
| IT / network contact | | | |
| Person who will install printer | | | |
| Person who will test printing | | | |

**Signed off by:** _______________________________  
**Date:** _______________________________

---

## Appendix A — Glossary (for client)

| Term | Meaning |
|------|---------|
| **VPS** | Your live website server in the cloud (not your office PC) |
| **ESC/POS** | Standard language thermal receipt printers understand |
| **Port 9100** | Network port used for raw receipt data to many thermal printers |
| **QZ Tray** | Free desktop app; lets websites print to local USB/network printers securely |
| **VPN** | Encrypted tunnel so VPS can reach devices on your office network safely |
| **Server-side print** | Laravel on VPS sends receipt directly to shop printer (no customer PC involved) |
| **Client-side print** | Customer’s browser/phone sends receipt to printer attached to their device |

---

## Appendix B — What we will configure after you respond

Based on your answers, we will configure:

| Your answer | Our implementation |
|-------------|-------------------|
| Shop auto-print + network printer + VPN | `NetworkThermalPrintService` on VPS, `.env` printer IP, optional auto-print job |
| Customer web thermal | QZ Tray + 80 mm preview fallback (current direction) |
| Customer mobile | Existing User app Bluetooth flow |
| No VPN, USB printer at shop only | QZ Tray on **one shop PC**, not pure VPS server-side |
| Arabic on receipt | Bitmap/encoding rules per printer capability |

---

## Appendix C — Quick checklist for client IT

Before go-live, confirm:

- [ ] Network thermal printer purchased and on LAN
- [ ] Printer IP is static (DHCP reservation)
- [ ] `telnet <printer-ip> 9100` works from a PC on shop LAN
- [ ] VPN tunnel: VPS can reach printer IP (test from VPS: `nc -zv <ip> 9100`)
- [ ] Firewall: port 9100 **not** exposed to public internet
- [ ] QZ Tray installed on PCs that need browser thermal print (if applicable)
- [ ] `php artisan qz-tray:generate-keys` run on production (for QZ signing)
- [ ] Test order placed end-to-end

---

*Return this completed document to your development team. We will propose final architecture and `.env` settings based on your answers.*
