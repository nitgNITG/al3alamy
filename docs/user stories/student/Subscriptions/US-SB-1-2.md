# US-SB-1-2 · Purchase a Subscription

**Epic:** Subscriptions
**Actor:** User (Student)

## Story

**As a** user,
**I want to** purchase a subscription,
**So that** I can access the courses and lessons included in it.

## Flow

- 🎓 **User** → Opens the **Subscriptions** page.
- 🎓 **User** → Selects a subscription.
- ⚙️ **System** → Displays:
  - Subscription name
  - Price
  - Included courses
  - Accessible lessons
  - Expiration information
- 🎓 **User** → Selects **Purchase Subscription**.
- ⚙️ **System** → Checks whether the user already has an active subscription.

**If the user has an active subscription:**

- ⚙️ **System** → Prevents the purchase.
- ⚙️ **System** → Displays that only one active subscription is allowed.

**If the user does not have an active subscription:**

- ⚙️ **System** → Redirects the user to the payment process.
- 🎓 **User** → Completes payment.

**If payment succeeds:**

- ⚙️ **System** → Activates the subscription.
- If the subscription uses **Duration in Days**:
  - ⚙️ **System** → Calculates the expiration date from the payment date and time.
- If the subscription uses **Specific Expiration Date**:
  - ⚙️ **System** → Uses the configured expiration date.
- ⚙️ **System** → Grants access to the courses and lessons included in the subscription.
- ⚙️ **System** → Records the payment.
- ⚙️ **System** → Adds the subscription to the user's subscription history.

**If payment fails:**

- ⚙️ **System** → Cancels the purchase.
- ⚙️ **System** → Displays the payment failure message.

## Business Rules

- A subscription becomes active only after successful payment.
- A user can have only one active subscription at a time.
- Users immediately gain access only to the courses and lessons included in the subscription.
- Lessons that are not included in the subscription remain inaccessible.
- Subscription updates performed by the admin also apply to active subscribers.
