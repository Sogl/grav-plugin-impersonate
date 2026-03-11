# v1.0.2
## 03/11/2026

1. [](#improved)
    * Switched impersonation token and status URLs from absolute to relative (`frontendUrl` instead of `frontendAbsoluteUrl`) to avoid host/scheme mismatches behind reverse proxies.

# v1.0.1
## 03/10/2026

1. [](#improved)
    * Fixed stale frontend impersonation state cleanup so Admin UI restores the impersonate button after an unexpected frontend logout/session loss.

# v1.0.0
##  02/19/2026

1. [](#new)
    * Impersonate first release.
