# US-AD-2-2 · Unsubscribe a User Manually

**Epic:** Manage Subscriptions
**Actor:** Admin

## Story

**As an** admin,
**I want to** manually unsubscribe a user from a specific subscription and specify whether the paid amount was returned,
**So that** I can revoke subscription access and keep earnings records accurate.

## Flow

- 🔧 **Admin** → Opens user management
- 🔧 **Admin** → Selects a user
- 🔧 **Admin** → Opens the user's active subscription
- 🔧 **Admin** → Selects **Unsubscribe User**
- 🔧 **Admin** → Selects the refund status:
  - Price Returned
  - Price Not Returned
- If **Price Returned** is selected:
  - 🔧 **Admin** → Enters the returned amount
- 🔧 **Admin** → Enters a required unsubscribe reason
- 🔧 **Admin** → Confirms the action
- ⚙️ **System** → Marks the user's subscription as manually unsubscribed
- ⚙️ **System** → Records the unsubscribe date and time
- ⚙️ **System** → Immediately revokes access to the subscription courses
- ⚙️ **System** → Records the admin who performed the action
- ⚙️ **System** → Records the unsubscribe reason
- ⚙️ **System** → Records whether the price was returned
- If the price was returned:
  - ⚙️ **System** → Deducts the returned amount from subscription earnings
- If the price was not returned:
  - ⚙️ **System** → Keeps the subscription earnings unchanged
- ⚙️ **System** → Adds the unsubscribe action to the user's subscription history

## Business Rules

- Manual unsubscribe takes effect immediately.
- The subscription record must not be permanently deleted.
- The subscription status becomes **Manually Unsubscribed**.
- The user loses access to all courses granted through the subscription.
- The user may subscribe to another subscription after the manual unsubscribe.
- The refund status must be selected before confirmation.
- The returned amount cannot be greater than the amount originally paid.
- A partial refund may be recorded by entering an amount lower than the originally paid amount.
- If no amount was returned, subscription earnings remain unchanged.
- The action, reason, refund status, and returned amount are permanently stored.
- Unsubscribing a user does not delete their course activity or subscription history.
