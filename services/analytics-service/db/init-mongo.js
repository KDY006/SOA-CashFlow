// Initialize MongoDB for Analytics Service
db = db.getSiblingDB('analytics_db');

db.createCollection('monthly_reports');
db.createCollection('spending_trends');
db.createCollection('user_budgets');

db.monthly_reports.createIndex({ "user_id": 1, "year": 1, "month": 1 }, { unique: true });
db.spending_trends.createIndex({ "user_id": 1, "category": 1 });

print("Analytics MongoDB initialized successfully.");
