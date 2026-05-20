## 2.0.5

**Released on:** 2026-02-10

**Compatibility note:** This version is compatible **from Moodle 5.0 to Moodle 5.1**.

## Fixed
- **Fatal error when only course generation allowlist was considered**  
  Corrected the rate limit user check that previously only evaluated the
  `local_coursegen` course creator list, which could cause incorrect
  access validation or fatal errors when other services or actions were
  configured.

## Added
- **Service/action-specific allowlist handling**  
  Extended the rate limiter so each AI-enabled service can declare its own
  user allowlist per HTTP action path (for example, `/course/v2/start` vs
  `/resources/create-mod`), keeping the access rules for different actions
  completely independent.

## Changed
- **Centralised helpers and internal clean-up**  
  Introduced small internal helpers to map paths to configuration keys and
  to extract user ids from configuration, reducing duplication and making
  future changes easier to maintain.
- **Version bump**  
  Release version bumped to **2.0.5**.

## 2.0.4

**Released on:** 2026-01-29

**Compatibility note:** This version is compatible **from Moodle 5.0 to Moodle 5.1**.

## Fixed
- **Suppress developer debug warning when listing rate-limited users**  
  Updated the rate-limit user selector query to load all required name fields (`firstnamephonetic`, `lastnamephonetic`, `middlename`, `alternatename`) so that `fullname()` no longer triggers the developer `debugging()` warning when building the allowed users lists.


## 2.0.3

**Released on:** 2026-01-26

**Compatibility note:** This version is compatible **from Moodle 5.0 to Moodle 5.1**.

## Added
- **Configurable base URLs for DataCurso AI services**  
  Added support for configurable base URLs for both the **standard** and **EU-hosted** DataCurso AI services, allowing greater flexibility across environments.
- **Optional base URL parameters in constructors**  
  Updated service constructors to accept optional base URL parameters, enabling explicit overrides when needed.
- **CHANGE.md file for change history**  
  Added a new **CHANGE.md** file to maintain a clear, versioned history of changes and releases.


## Changed
- **Centralized base URL resolution via instance method**  
  Refactored base URL access to ensure the correct instance method is used when resolving the active base URL, improving consistency and maintainability.
- **Service initialization flow updated**  
  Adjusted internal initialization logic so all API requests correctly respect the configured base URL (standard or EU-hosted).
- **Version bump**  
  Release version bumped to **2.0.3**.


## 2.0.2

**Released on:** 2026-01-19

## Added

- **Enhanced webservice setup error logging.**  
Improved error reporting during webservice registration by including the original exception message, providing clearer diagnostics when the setup process fails.

## Changed

- **Improved boolean evaluation logic.**  
Adjusted the `is_for_ue` method to ensure proper and safe boolean comparison, preventing unintended conditional behavior.

## Fixed

- **Webservice setup debugging limitations.**  
Resolved an issue where webservice registration failures did not expose sufficient context, making troubleshooting difficult.

## Changed

- **Release bump to 2.0.2**  
Updated the plugin version and release metadata to **2.0.2** to reflect the included improvements and fixes.

## 2.0.1

**Released on:** 2025-12-15

**Compatibility note:** This version is compatible **only with Moodle 5.0 and 5.1**.

## Added

- **User-level AI credit usage limits.** 
Introduced functionality to control and restrict AI credit consumption on a per-user basis within the Datacurso provider.

## Changed

- **Support updated to Moodle 5.0.** 
The plugin now targets the **MOODLE_500_STABLE** branch and is aligned with Moodle 5.0 and 5.1

- **Updated core API and rate limiter logic.** 
Improved the `datacurso_api_base` class and rate limiter implementation to support user-specific credit limits.

- **Updated provider language strings.** 
Revised and improved provider strings for better clarity and consistency.

- **Code quality and linting improvements.** 
Fixed linting issues and applied coding standard adjustments in provider and form-related files.

## Fixed

- **String and character issues.** 
Resolved issues related to string definitions that could cause compilation or display problems.

- **General linting errors.** 
Addressed additional linter warnings and errors across the codebase.

## Changed

- **Release bump to 2.0.1** 
Updated the plugin release number to **2.0.1**.

