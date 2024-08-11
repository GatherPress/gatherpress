# e2e testing

GatherPress allows to run automated and manual end-to-end tests, while sharing the same, `wp-env` based, setup.

## Automated tests 

Check the results of the _e2e-tests action workflow_ at  `https://github.com/GatherPress/gatherpress/actions/workflows/e2e-tests.yml`.

## Manual testing

0. Have `node` installed
1. Have `docker` running
2. Open the plugin folder in a terminal
3. _Choose one of the following options_
   - Run Playwright in the background using
      ```
      npm run test:e2e
      ```
       
   - Run Playwright visually (and change what's happening) using
      ```
      npm run test:e2e:debug
      ```
      ![grafik](https://github.com/user-attachments/assets/1627dff7-363e-447e-9981-adac610ac888)

   - Run Tests independently _AND_ visually using the [Playwright VSCode extension](https://playwright.dev/docs/getting-started-vscode)