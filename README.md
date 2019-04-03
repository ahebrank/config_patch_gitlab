# Submit configuration patches to a Gitlab repo as a merge request

This is an output plugin for [config_patch](https://drupal.org/project/config_patch). Gitlab provides a [special email address to submit merge requests](https://docs.gitlab.com/ee/user/project/merge_requests/#create-new-merge-requests-by-email) that lets users submit patches and automatically create merge requests. This means a sitebuilding Drupal user may make a config change in the UI and then create a merge request with that config in the source repository.

This module has a dependency on [Swiftmailer](https://drupal.org/project/swiftmailer) to send patches as email attachments.