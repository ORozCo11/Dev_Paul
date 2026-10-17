# Frontend lint ledger ? 15 October 2026

56 errors; 19 warnings. These are static diagnostics, not independently reproduced runtime bugs. Full diagnostic messages and source excerpts are retained in the corresponding JSON file.

| Severity | File:line | Rule | Diagnostic |
|---|---|---|---|
| Error | frontend-spa/src/components/Icon.jsx:201 | no-dupe-keys | Duplicate key 'search'. |
| Error | frontend-spa/src/components/Icon.jsx:310 | react-refresh/only-export-components | Fast refresh only works when a file only exports components. Use a new file to share constants or functions between components. |
| Error | frontend-spa/src/components/LocationDensityMap.jsx:359 | react-hooks/set-state-in-effect | Error: Calling setState synchronously within an effect can trigger cascading renders |
| Error | frontend-spa/src/components/TextType.jsx:153 | react-hooks/refs | Error: Cannot access refs during render |
| Error | frontend-spa/src/utils/geocode.js:19 | preserve-caught-error | There is no `cause` attached to the symptom error being thrown. |
| Error | frontend-spa/src/utils/geocode.js:21 | preserve-caught-error | There is no `cause` attached to the symptom error being thrown. |
| Error | frontend-spa/src/views/Workspace.jsx:18 | no-unused-vars | 'SolidPieChart' is defined but never used. |
| Warning | frontend-spa/src/views/Workspace.jsx:576 | react-hooks/exhaustive-deps | React Hook useMemo has a missing dependency: 'user'. Either include it or remove the dependency array. |
| Error | frontend-spa/src/views/Workspace.jsx:706 | react-hooks/purity | Error: Cannot call impure function during render |
| Error | frontend-spa/src/views/Workspace.jsx:833 | react-hooks/set-state-in-effect | Error: Calling setState synchronously within an effect can trigger cascading renders |
| Error | frontend-spa/src/views/Workspace.jsx:895 | react-hooks/set-state-in-effect | Error: Calling setState synchronously within an effect can trigger cascading renders |
| Error | frontend-spa/src/views/Workspace.jsx:1031 | react-hooks/set-state-in-effect | Error: Calling setState synchronously within an effect can trigger cascading renders |
| Error | frontend-spa/src/views/Workspace.jsx:1037 | react-hooks/set-state-in-effect | Error: Calling setState synchronously within an effect can trigger cascading renders |
| Error | frontend-spa/src/views/Workspace.jsx:1082 | react-hooks/set-state-in-effect | Error: Calling setState synchronously within an effect can trigger cascading renders |
| Error | frontend-spa/src/views/Workspace.jsx:1086 | react-hooks/set-state-in-effect | Error: Calling setState synchronously within an effect can trigger cascading renders |
| Warning | frontend-spa/src/views/Workspace.jsx:1261 | react-hooks/exhaustive-deps | The 'handleCreateTicketFromIssue' function makes the dependencies of useMemo Hook (at line 2314) change on every render. To fix this, wrap the definition of 'handleCreateTicketFromIssue' in its own useCallback() Hook. |
| Warning | frontend-spa/src/views/Workspace.jsx:1297 | react-hooks/exhaustive-deps | The 'handleCreateTicketFromCondition' function makes the dependencies of useMemo Hook (at line 2307) change on every render. Move it inside the useMemo callback. Alternatively, wrap the definition of 'handleCreateTicketFromCondition' in its own useCallback() Hook. |
| Warning | frontend-spa/src/views/Workspace.jsx:1315 | react-hooks/exhaustive-deps | The 'handleSuggestScheduleFromCondition' function makes the dependencies of useMemo Hook (at line 2307) change on every render. Move it inside the useMemo callback. Alternatively, wrap the definition of 'handleSuggestScheduleFromCondition' in its own useCallback() Hook. |
| Warning | frontend-spa/src/views/Workspace.jsx:1346 | react-hooks/exhaustive-deps | The 'updateRecord' function makes the dependencies of useMemo Hook (at line 2320) change on every render. To fix this, wrap the definition of 'updateRecord' in its own useCallback() Hook. |
| Warning | frontend-spa/src/views/Workspace.jsx:1372 | react-hooks/exhaustive-deps | The 'deleteRecord' function makes the dependencies of useMemo Hook (at line 2272) change on every render. To fix this, wrap the definition of 'deleteRecord' in its own useCallback() Hook. |
| Warning | frontend-spa/src/views/Workspace.jsx:1372 | react-hooks/exhaustive-deps | The 'deleteRecord' function makes the dependencies of useMemo Hook (at line 2283) change on every render. To fix this, wrap the definition of 'deleteRecord' in its own useCallback() Hook. |
| Warning | frontend-spa/src/views/Workspace.jsx:1372 | react-hooks/exhaustive-deps | The 'deleteRecord' function makes the dependencies of useMemo Hook (at line 2307) change on every render. To fix this, wrap the definition of 'deleteRecord' in its own useCallback() Hook. |
| Warning | frontend-spa/src/views/Workspace.jsx:1372 | react-hooks/exhaustive-deps | The 'deleteRecord' function makes the dependencies of useMemo Hook (at line 2314) change on every render. To fix this, wrap the definition of 'deleteRecord' in its own useCallback() Hook. |
| Warning | frontend-spa/src/views/Workspace.jsx:1390 | react-hooks/exhaustive-deps | The 'toggleUserActive' function makes the dependencies of useMemo Hook (at line 2289) change on every render. Move it inside the useMemo callback. Alternatively, wrap the definition of 'toggleUserActive' in its own useCallback() Hook. |
| Warning | frontend-spa/src/views/Workspace.jsx:1438 | react-hooks/exhaustive-deps | The 'restoreRecord' function makes the dependencies of useMemo Hook (at line 2272) change on every render. To fix this, wrap the definition of 'restoreRecord' in its own useCallback() Hook. |
| Warning | frontend-spa/src/views/Workspace.jsx:2055 | react-hooks/exhaustive-deps | React Hook useMemo has a missing dependency: 'user.id'. Either include it or remove the dependency array. |
| Warning | frontend-spa/src/views/Workspace.jsx:2337 | react-hooks/exhaustive-deps | React Hook useMemo has missing dependencies: 'approveSchedule' and 'openTicketProfile'. Either include them or remove the dependency array. |
| Warning | frontend-spa/src/views/Workspace.jsx:2402 | react-hooks/exhaustive-deps | React Hook useMemo has a missing dependency: 'ticketAction'. Either include it or remove the dependency array. |
| Error | frontend-spa/src/views/Workspace.jsx:2550 | react-hooks/set-state-in-effect | Error: Calling setState synchronously within an effect can trigger cascading renders |
| Error | frontend-spa/src/views/Workspace.jsx:2690 | react-hooks/set-state-in-effect | Error: Calling setState synchronously within an effect can trigger cascading renders |
| Error | frontend-spa/src/views/Workspace.jsx:5002 | no-unused-vars | 'SolidPieLegend' is defined but never used. |
| Error | frontend-spa/src/views/Workspace.jsx:5198 | react-hooks/set-state-in-effect | Error: Calling setState synchronously within an effect can trigger cascading renders |
| Error | frontend-spa/src/views/Workspace.jsx:5206 | react-hooks/set-state-in-effect | Error: Calling setState synchronously within an effect can trigger cascading renders |
| Error | frontend-spa/src/views/Workspace.jsx:5227 | no-unused-vars | 'historyTotal' is assigned a value but never used. |
| Error | frontend-spa/src/views/Workspace.jsx:5905 | react-hooks/set-state-in-effect | Error: Calling setState synchronously within an effect can trigger cascading renders |
| Warning | frontend-spa/src/views/Workspace.jsx:5906 | react-hooks/exhaustive-deps | React Hook useEffect has a missing dependency: 'fields'. Either include it or remove the dependency array. If 'setValues' needs the current value of 'fields', you can also switch to useReducer instead of useState and read 'fields' in the reducer. |
| Error | frontend-spa/src/views/Workspace.jsx:5921 | no-unused-vars | '_gone' is defined but never used. |
| Error | frontend-spa/src/views/Workspace.jsx:6889 | no-unused-vars | 'categoryFields' is assigned a value but never used. |
| Error | frontend-spa/src/views/Workspace.jsx:6907 | no-unused-vars | 'userFields' is defined but never used. |
| Error | frontend-spa/src/views/Workspace.jsx:7508 | no-unused-vars | 'VehicleCannibalizationCard' is defined but never used. |
| Warning | frontend-spa/src/views/Workspace.jsx:7993 | react-hooks/exhaustive-deps | React Hook useEffect has a missing dependency: 'setLiveValues'. Either include it or remove the dependency array. |
| Error | frontend-spa/src/views/Workspace.jsx:8005 | react-hooks/set-state-in-effect | Error: Calling setState synchronously within an effect can trigger cascading renders |
| Error | frontend-spa/src/views/Workspace.jsx:8667 | no-unused-vars | 'ISSUE_SEVERITY_CHIP_BG' is assigned a value but never used. |
| Error | frontend-spa/src/views/Workspace.jsx:8668 | no-unused-vars | 'ISSUE_SEVERITY_CHIP_COLOR' is assigned a value but never used. |
| Error | frontend-spa/src/views/Workspace.jsx:9319 | react-hooks/set-state-in-effect | Error: Calling setState synchronously within an effect can trigger cascading renders |
| Warning | frontend-spa/src/views/Workspace.jsx:9328 | eslint directive | Unused eslint-disable directive (no problems were reported from 'react-hooks/exhaustive-deps'). |
| Error | frontend-spa/src/views/Workspace.jsx:9337 | no-unused-vars | 'toggleDraftStatus' is assigned a value but never used. |
| Warning | frontend-spa/src/views/Workspace.jsx:9496 | eslint directive | Unused eslint-disable directive (no problems were reported from 'react-hooks/exhaustive-deps'). |
| Error | frontend-spa/src/views/Workspace.jsx:10190 | react-hooks/set-state-in-effect | Error: Calling setState synchronously within an effect can trigger cascading renders |
| Error | frontend-spa/src/views/Workspace.jsx:10813 | react-hooks/set-state-in-effect | Error: Calling setState synchronously within an effect can trigger cascading renders |
| Error | frontend-spa/src/views/Workspace.jsx:11030 | no-unused-vars | 'subRowFields' is defined but never used. |
| Error | frontend-spa/src/views/Workspace.jsx:11933 | no-unused-vars | 'VerificationForm' is defined but never used. |
| Error | frontend-spa/src/views/Workspace.jsx:13341 | react-hooks/set-state-in-effect | Error: Calling setState synchronously within an effect can trigger cascading renders |
| Error | frontend-spa/src/views/Workspace.jsx:13470 | react-hooks/set-state-in-effect | Error: Calling setState synchronously within an effect can trigger cascading renders |
| Error | frontend-spa/src/views/Workspace.jsx:13482 | react-hooks/set-state-in-effect | Error: Calling setState synchronously within an effect can trigger cascading renders |
| Error | frontend-spa/src/views/Workspace.jsx:13923 | no-unused-vars | 'updatePartRow' is assigned a value but never used. |
| Error | frontend-spa/src/views/Workspace.jsx:13927 | no-unused-vars | 'addPartRow' is assigned a value but never used. |
| Error | frontend-spa/src/views/Workspace.jsx:13928 | no-unused-vars | 'removePartRow' is assigned a value but never used. |
| Error | frontend-spa/src/views/Workspace.jsx:13950 | no-unused-vars | 'toggleDonorPart' is assigned a value but never used. |
| Error | frontend-spa/src/views/Workspace.jsx:14058 | react-hooks/set-state-in-effect | Error: Calling setState synchronously within an effect can trigger cascading renders |
| Warning | frontend-spa/src/views/Workspace.jsx:14069 | react-hooks/exhaustive-deps | React Hook useEffect has a missing dependency: 'setLiveValues'. Either include it or remove the dependency array. |
| Error | frontend-spa/src/views/Workspace.jsx:14347 | no-unused-vars | 'onViewVehicle' is defined but never used. |
| Error | frontend-spa/src/views/Workspace.jsx:14634 | no-unused-vars | 'VehicleReliabilityCard' is defined but never used. |
| Error | frontend-spa/src/views/Workspace.jsx:14640 | react-hooks/set-state-in-effect | Error: Calling setState synchronously within an effect can trigger cascading renders |
| Error | frontend-spa/src/views/Workspace.jsx:15004 | no-unused-vars | 'canViewReliability' is assigned a value but never used. |
| Error | frontend-spa/src/views/Workspace.jsx:15004 | no-unused-vars | 'canViewUsage' is assigned a value but never used. |
| Error | frontend-spa/src/views/Workspace.jsx:15004 | no-unused-vars | 'canLogUsage' is assigned a value but never used. |
| Error | frontend-spa/src/views/Workspace.jsx:15004 | no-unused-vars | 'currentUser' is assigned a value but never used. |
| Error | frontend-spa/src/views/Workspace.jsx:15022 | react-hooks/set-state-in-effect | Error: Calling setState synchronously within an effect can trigger cascading renders |
| Error | frontend-spa/src/views/Workspace.jsx:15037 | react-hooks/set-state-in-effect | Error: Calling setState synchronously within an effect can trigger cascading renders |
| Error | frontend-spa/src/views/Workspace.jsx:15591 | no-unused-vars | 'onViewVehicle' is defined but never used. |
| Error | frontend-spa/src/views/Workspace.jsx:16976 | react-hooks/set-state-in-effect | Error: Calling setState synchronously within an effect can trigger cascading renders |
| Error | frontend-spa/src/views/Workspace.jsx:17639 | react-hooks/set-state-in-effect | Error: Calling setState synchronously within an effect can trigger cascading renders |
| Error | frontend-spa/src/views/Workspace.jsx:18210 | no-unused-vars | 'VehicleUsageCard' is defined but never used. |
| Error | frontend-spa/src/views/Workspace.jsx:18353 | react-hooks/set-state-in-effect | Error: Calling setState synchronously within an effect can trigger cascading renders |
