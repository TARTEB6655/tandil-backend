# Remaining APIs to Be Developed (Role Dashboards)

Total New APIs Required: **106**

---

## 1) Technician Dashboard (32 APIs)

### Dashboard Overview
1. `GET /api/technician/dashboard/summary`
2. `GET /api/technician/dashboard/kpis`
3. `GET /api/technician/dashboard/upcoming-visits`
4. `GET /api/technician/dashboard/alerts`

### Profile & Settings
5. `GET /api/technician/profile`
6. `PUT /api/technician/profile`
7. `PUT /api/technician/profile/password`
8. `GET /api/technician/settings`
9. `PUT /api/technician/settings`

### Earnings & Payout
10. `GET /api/technician/earnings/summary`
11. `GET /api/technician/earnings/history`
12. `GET /api/technician/payouts`
13. `POST /api/technician/payouts/request`
14. `GET /api/technician/payouts/{id}`

### Bank Accounts
15. `GET /api/technician/bank-accounts`
16. `POST /api/technician/bank-accounts`
17. `PUT /api/technician/bank-accounts/{id}`
18. `DELETE /api/technician/bank-accounts/{id}`

### Availability & Scheduling (availability includes service_area and breaks; no separate break endpoints)
19. `GET /api/technician/availability`
20. `PUT /api/technician/availability`
21. `GET /api/technician/schedule`
22. `POST /api/technician/vacations`

### Job History
24. `GET /api/technician/jobs/history`
25. `GET /api/technician/jobs/history/{id}`
26. `GET /api/technician/jobs/history/export`

### Tasks Management
27. `GET /api/technician/tasks`
28. `GET /api/technician/tasks/{id}`
29. `PUT /api/technician/tasks/{id}/status`
30. `POST /api/technician/tasks/{id}/accept-reject`

### Help & Support
31. `GET /api/technician/support/help-center`
32. `POST /api/technician/support/tickets`

---

## 2) Area Manager Dashboard (24 APIs)

### Dashboard Overview
33. `GET /api/area-manager/dashboard/summary`
34. `GET /api/area-manager/dashboard/kpis`
35. `GET /api/area-manager/dashboard/active-regions`
36. `GET /api/area-manager/dashboard/alerts`

### Team Leaders Management
37. `GET /api/area-manager/team-leaders`
38. `POST /api/area-manager/team-leaders`
39. `GET /api/area-manager/team-leaders/{id}`
40. `PUT /api/area-manager/team-leaders/{id}`
41. `DELETE /api/area-manager/team-leaders/{id}`

### Region Map
42. `GET /api/area-manager/regions`
43. `GET /api/area-manager/regions/{id}/map-data`
44. `GET /api/area-manager/regions/{id}/live-status`
45. `GET /api/area-manager/regions/{id}/technicians`

### Analytics
46. `GET /api/area-manager/analytics/performance`
47. `GET /api/area-manager/analytics/visits`
48. `GET /api/area-manager/analytics/revenue`
49. `GET /api/area-manager/analytics/customer-satisfaction`

### Reports
50. `GET /api/area-manager/reports`
51. `POST /api/area-manager/reports/generate`
52. `GET /api/area-manager/reports/{id}`
53. `GET /api/area-manager/reports/{id}/download`

### Profile
54. `GET /api/area-manager/profile`
55. `PUT /api/area-manager/profile`
56. `PUT /api/area-manager/profile/password`

---

## 3) Supervisor Dashboard (20 APIs)

### Dashboard Overview
57. `GET /api/supervisor/dashboard/summary`
58. `GET /api/supervisor/dashboard/kpis`
59. `GET /api/supervisor/dashboard/team-status`
60. `GET /api/supervisor/dashboard/alerts`

### Team Statistics
61. `GET /api/supervisor/team/statistics`
62. `GET /api/supervisor/team/performance`
63. `GET /api/supervisor/team/attendance`
64. `GET /api/supervisor/team/workload`

### Assign Technician
65. `GET /api/supervisor/assignments/pending`
66. `POST /api/supervisor/assignments`
67. `PUT /api/supervisor/assignments/{id}`
68. `POST /api/supervisor/assignments/{id}/reassign`

### Reports
69. `GET /api/supervisor/reports`
70. `POST /api/supervisor/reports/generate`
71. `GET /api/supervisor/reports/{id}`
72. `GET /api/supervisor/reports/{id}/download`

### Profile
73. `GET /api/supervisor/profile`
74. `PUT /api/supervisor/profile`
75. `PUT /api/supervisor/profile/password`
76. `GET /api/supervisor/profile/preferences`

---

## 4) HR Manager Dashboard (30 APIs)

### Dashboard Overview
77. `GET /api/hr/dashboard/summary`
78. `GET /api/hr/dashboard/kpis`
79. `GET /api/hr/dashboard/attendance-overview`
80. `GET /api/hr/dashboard/alerts`

### Employee Management Expansion
81. `GET /api/hr/employees`
82. `POST /api/hr/employees`
83. `GET /api/hr/employees/{id}`
84. `PUT /api/hr/employees/{id}`
85. `DELETE /api/hr/employees/{id}`
86. `POST /api/hr/employees/{id}/status`

### Leave Request System
87. `GET /api/hr/leaves`
88. `POST /api/hr/leaves`
89. `GET /api/hr/leaves/{id}`
90. `PUT /api/hr/leaves/{id}`
91. `DELETE /api/hr/leaves/{id}`
92. `GET /api/hr/leaves/balance/{employeeId}`

### Leave Approve/Reject
93. `POST /api/hr/leaves/{id}/approve`
94. `POST /api/hr/leaves/{id}/reject`
95. `POST /api/hr/leaves/{id}/cancel`
96. `GET /api/hr/leaves/pending-approvals`

### Visit Assignments
97. `GET /api/hr/visit-assignments`
98. `POST /api/hr/visit-assignments`
99. `PUT /api/hr/visit-assignments/{id}`
100. `DELETE /api/hr/visit-assignments/{id}`

### Reports
101. `GET /api/hr/reports`
102. `POST /api/hr/reports/generate`
103. `GET /api/hr/reports/{id}/download`

### Profile
104. `GET /api/hr/profile`
105. `PUT /api/hr/profile`
106. `PUT /api/hr/profile/password`

---

## Final Count

- Technician: **32**
- Area Manager: **24**
- Supervisor: **20**
- HR Manager: **30**

**Grand Total Remaining APIs: 106**

