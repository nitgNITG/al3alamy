# US-AD-1-3 · Deactivate or Delete a Subscription Plan

**Epic:** Manage Subscriptions
**Actor:** Admin

## Story

**As an** admin,
**I want to** deactivate or delete a subscription plan,
**So that** I can manage subscription availability appropriately.

## Flow

- 🔧 **Admin** → Opens subscription management
- 🔧 **Admin** → Selects a subscription

### Option 1 — Deactivate Subscription

- 🔧 **Admin** → Selects **Deactivate Subscription**
- 🔧 **Admin** → Confirms the action
- ⚙️ **System** → Marks the subscription as inactive
- ⚙️ **System** → Removes it from the list of available subscriptions

### Option 2 — Delete Subscription

- 🔧 **Admin** → Selects **Delete Subscription**
- ⚙️ **System** → Checks whether any user has purchased the subscription

**If no user has purchased the subscription:**

- 🔧 **Admin** → Confirms deletion
- ⚙️ **System** → Permanently deletes the subscription

**If at least one user has purchased the subscription:**

- ⚙️ **System** → Prevents deletion
- ⚙️ **System** → Displays a message that purchased subscriptions must be deactivated instead

## Business Rules

- Deactivated subscriptions cannot be purchased.
- Existing subscribers keep access until their subscriptions expire or are otherwise updated.
- A subscription can be deleted only if it has never been purchased by any user.
- Deletion permanently removes the subscription and cannot be undone.
- A deactivated subscription can be reactivated later.
