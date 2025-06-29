# 💳 Juspay Flask Integration Kit

This project is a Python-based backend using **Flask** that demonstrates how to integrate **Juspay's Payment Gateway** using **API Key-based authentication**. It allows merchants to initiate a payment session, handle payment responses, check order status, and process refunds.

---

## 📦 Features

- Initiate Juspay session and redirect users to hosted payment page
- Handle return URL and show order status
- Fetch order status using order ID
- Process refund requests
- Logs for debugging and tracking


---

## 🛠 Tech Stack

- **Backend**: Python, Flask
---

## 📁 Project Structure
project-root/
├── index.py # Main Flask application
├── payment_handler.py # Juspay API integration logic
├── config.json # Merchant configuration
├── public/
│ └── initatePaymentDataForm.html # to create order and initiate payment display page.
| └── initateRefundDataForm.html  # to initate refund for given order.
└── README.md # Project documentation
