# Category manual tests

Use the common setup in [manual-api-tests.md](./manual-api-tests.md). Set the
Admin access token using the login instructions in
[auth-test.md](./auth-test.md).

## Admin category operations

### C1. Create a category

```sh
curl -i -H "Authorization: Bearer $ADMIN_ACCESS_TOKEN" \
  -H 'Content-Type: application/json' \
  -d '{"name":"Manual Test Category","slug":"manual-test-category","position":10,"is_active":true}' \
  "$ADMIN_BASE/api/admin/categories"
```

**Expect:** `201`; save the returned category `id`:

```sh
export CATEGORY_ID='paste-category-id'
```

### C2. Read category list and individual category

```sh
curl -i -H "Authorization: Bearer $ADMIN_ACCESS_TOKEN" "$ADMIN_BASE/api/admin/categories"
curl -i -H "Authorization: Bearer $ADMIN_ACCESS_TOKEN" "$ADMIN_BASE/api/admin/categories/$CATEGORY_ID"
```

**Expect:** `200`; the created category is present in both responses.

### C3. Update category

```sh
curl -i -X PATCH -H "Authorization: Bearer $ADMIN_ACCESS_TOKEN" \
  -H 'Content-Type: application/json' \
  -d '{"name":"Updated Manual Category","position":11}' \
  "$ADMIN_BASE/api/admin/categories/$CATEGORY_ID"
```

**Expect:** `200` with the updated name and position.

### C4. Reject duplicate slug and cross-tenant parent

```sh
curl -i -H "Authorization: Bearer $ADMIN_ACCESS_TOKEN" \
  -H 'Content-Type: application/json' \
  -d '{"name":"Duplicate","slug":"manual-test-category"}' \
  "$ADMIN_BASE/api/admin/categories"
```

**Expect:** `422`. If a category ID from another tenant is available, attempt
to set it as `parent_id`; expect validation failure and no cross-tenant data
disclosure.

### C5. Delete category

Delete only after removing or reassigning dependent products/child categories:

```sh
curl -i -X DELETE -H "Authorization: Bearer $ADMIN_ACCESS_TOKEN" \
  "$ADMIN_BASE/api/admin/categories/$CATEGORY_ID"
```

**Expect:** `204`. If dependencies remain, expect `409`.

## Storefront categories

```sh
curl -i "$STORE_BASE/api/storefront/catalog/categories"
```

**Expect:** `200`; only active categories from this tenant are returned.

## Result record

| Case | Result (PASS/FAIL/BLOCKED) | Actual status | Notes |
|---|---|---:|---|
| C1-C3 create/read/update | | | |
| C4 duplicate/cross-tenant rejection | | | |
| C5 delete/dependency protection | | | |
| Storefront category list | | | |
