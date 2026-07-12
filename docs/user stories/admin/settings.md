# Admin · System Settings

## US-AD-11-1 · Configure Device Registration Settings

**Epic:** System Settings
**Actor:** Admin

### Story

**As an** admin,
**I want to** configure the maximum number of registered devices allowed per user,
**So that** I can control how many devices each user can use to access the platform.

### Flow

- 🔧 **Admin** → Opens **System Settings**
- 🔧 **Admin** → Opens **Device Registration Settings**
- ⚙️ **System** → Displays the current device registration configuration, including:
  - Enable/Disable device registration control
  - Maximum registered devices allowed per user
- 🔧 **Admin** → Enables or disables the feature
- 🔧 **Admin** → Sets the maximum number of registered devices allowed for each user
- 🔧 **Admin** → Saves the changes
- ⚙️ **System** → Validates the entered value
- ⚙️ **System** → Stores the updated configuration
- ⚙️ **System** → Applies the new configuration to future login attempts

### Business Rules

- When the feature is disabled, users can register unlimited devices.
- The maximum number of devices must be greater than zero when the feature is enabled.
- Updating the maximum limit does not automatically remove previously registered devices.
- Existing registered devices remain associated with their users until removed.
- The configured limit is enforced whenever a user attempts to register a new device.
