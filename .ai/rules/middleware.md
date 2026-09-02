---
paths:
  - 'app/Http/Middleware/**'
---

# Middleware

## Backward compatible changes only
All changes must be backward compatible with existing production clients. Never add required headers, change response formats, or alter request/response contracts without explicit approval. Use opt-in patterns (e.g., skip behavior when header is absent) rather than breaking changes.
