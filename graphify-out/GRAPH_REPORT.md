# Graph Report - .  (2026-09-01)

## Corpus Check
- cluster-only mode — file stats not available

## Summary
- 8013 nodes · 25680 edges · 271 communities (226 shown, 45 thin omitted)
- Extraction: 88% EXTRACTED · 12% INFERRED · 0% AMBIGUOUS · INFERRED: 3111 edges (avg confidence: 0.74)
- Token cost: 0 input · 0 output

## Graph Freshness
- Built from commit: `066e96a2`
- Run `git rev-parse HEAD` and compare to check if the graph is stale.
- Run `graphify update .` after code changes (no API cost).

## Community Hubs (Navigation)
- rich-editor.js
- Illuminate\Database\Eloquent\Builder
- code-editor.js
- components/chart.js
- User
- TestCase
- Office
- update
- markdown-editor.js
- stat/chart.js
- resolve
- Illuminate\Database\Eloquent\Relations\BelongsTo
- prop
- ApproverMapping
- fromObject
- Illuminate\Database\Eloquent\Model
- .slice
- _update
- Vendor
- ProcurementItem
- get
- o
- InvoiceResource
- e
- by
- i
- ProcurementCategory
- draw
- create
- Illuminate\Database\Eloquent\Factories\Factory
- t
- file-upload.js
- slice
- lineAt
- tables.js
- E
- parse
- toString
- facet
- eq
- go
- columns/select.js
- add
- PurchaseOrder
- notifications.js
- support.js
- constructor
- u
- slider.js
- reduce
- te
- create
- Ye
- getContext
- parse
- find
- constructor
- I
- BudgetReservationService
- draw
- oi
- ValidationException
- jt
- jh
- components/select.js
- _update
- fn
- parse
- Cn
- UmrahBatch
- r
- addEventListener
- Distribution
- echo.js
- Xt
- DistributionItem
- InvoiceMatchingService
- Pilgrim
- DistributionService
- A
- ApprovalInstanceStep
- SampleShipmentReceiptService
- g$
- _each
- SampleShipment
- ProcurementReviewService
- W
- S
- WorkflowResource
- Illuminate\Database\Eloquent\Relations\HasMany
- AttachmentService
- package.json
- eq
- ls
- ar
- Cs
- ProcurementCategoryConfiguration
- GoodsReceipt
- ProcurementField
- En
- ApprovalTaskLifecycleService
- color-picker.js
- toString
- constructor
- PilgrimImporter
- filament/app.js
- ul
- ApprovalTaskAssigned
- CategoryRelationGuard
- closeDropdown
- closeDropdown
- fn
- DynamicFieldValidator
- Login.php
- HealthController.php
- KeycloakController.php
- Closure
- scripts
- fn
- ApprovalInstance
- composer.json
- Filament\Resources\Pages\ManageRecords
- Illuminate\Database\Migrations\Migration
- t
- selectOption
- renderOptions
- renderOptions
- AdminPanelProvider.php
- .receivableOrder
- r
- .workflow
- KeycloakOidcCallbackTest
- DatePicker
- PilgrimAllocationsRelationManager
- Illuminate\Http\Request
- ProcurementFieldPolicy
- lt
- FoundationTest
- WorkflowBindingSelector
- command
- actions/actions.js
- schemas.js
- BudgetPolicy
- EnvironmentValidationTest
- require-dev
- ProcurementFieldResource
- RedactSensitiveData
- setup
- PilgrimFactory
- Illuminate\Console\Command
- GoodsReceiptsRelationManager
- config
- require
- UmrahBatchFactory
- nr
- .budgetAndRequest
- .financeTask
- i
- components/actions.js
- DepartureBatchResource
- FinanceApprovalDetail
- psr-4
- ot
- ManageBranches
- ManageBudgets
- ManageCostCenters
- ManageDepartments
- ManageOffices
- ManagePilgrims
- ManageProcurementFields
- ManageUmrahBatches
- ManageUserAssignments
- KeycloakUserProvisioner
- post-create-project-cmd
- 2026_08_29_191404_add_filament_logger_indexes_to_activity_log_table.php
- ExampleTest
- ManageProcurementCategories.php
- ManageProcurementUnits.php
- ManageProcurementVariants.php
- ManageVendors.php
- ManageWorkflowSteps.php
- ManageWorkflowVersions.php
- extra
- laravel-boost
- laravel-boost

## God Nodes (most connected - your core abstractions)
1. `User` - 608 edges
2. `PurchaseRequest` - 203 edges
3. `o()` - 178 edges
4. `TestCase` - 174 edges
5. `Office` - 173 edges
6. `t()` - 154 edges
7. `UserAssignment` - 151 edges
8. `i()` - 147 edges
9. `update()` - 146 edges
10. `constructor()` - 144 edges

## Surprising Connections (you probably didn't know these)
- `xQ()` --indirect_call--> `ay()`  [INFERRED]
  public/js/filament/forms/components/code-editor.js → public/js/filament/forms/components/rich-editor.js
- `constructor()` --indirect_call--> `i()`  [INFERRED]
  public/js/filament/forms/components/color-picker.js → public/js/filament/forms/components/date-time-picker.js
- `[x]()` --indirect_call--> `H()`  [INFERRED]
  public/js/filament/forms/components/color-picker.js → public/js/filament/forms/components/markdown-editor.js
- `_freeze()` --indirect_call--> `t()`  [INFERRED]
  public/js/filament/forms/components/file-upload.js → public/js/filament/forms/components/date-time-picker.js
- `getExtension()` --indirect_call--> `qt()`  [INFERRED]
  public/js/filament/forms/components/file-upload.js → public/js/filament/forms/components/rich-editor.js

## Import Cycles
- None detected.

## Communities (271 total, 45 thin omitted)

### Community 0 - "rich-editor.js"
Cohesion: 0.01
Nodes (163): themeClasses(), aa(), addHackNode(), addNodeMark(), addTextblockHacks(), applyAspectRatio(), applyConstraints(), atEnd() (+155 more)

### Community 1 - "Illuminate\Database\Eloquent\Builder"
Cohesion: 0.02
Nodes (80): ApprovalInboxResource, BackedEnum, UnitEnum, ApproverDelegationResource, UnitEnum, ApproverDelegationForm, ApproverDelegationsTable, ApproverMappingResource (+72 more)

### Community 2 - "code-editor.js"
Cohesion: 0.01
Nodes (130): Ac(), addCompletion(), addCompletions(), addNamespace(), addNamespaceObject(), Ag(), ATXHeading(), Blockquote() (+122 more)

### Community 3 - "components/chart.js"
Cohesion: 0.01
Nodes (101): _a(), abutsStart(), ac(), addControllers(), addPlugins(), addScales(), afterDraw(), Bt() (+93 more)

### Community 4 - "User"
Cohesion: 0.02
Nodes (33): check(), PurchaseRequest, Quotation, User, ActivityPolicy, ApprovalInstanceStepPolicy, DistributionItemPolicy, PurchaseRequestPolicy (+25 more)

### Community 5 - "TestCase"
Cohesion: 0.04
Nodes (43): label(), options(), Activity, App\Models\PurchaseOrderItem, AppServiceProvider, AccessContextService, PurchaseRequestNumberService, App\Support\DomainTransaction (+35 more)

### Community 6 - "Office"
Cohesion: 0.02
Nodes (36): UnitEnum, QuotationResource, Office, Permission, LogOptions, Role, UserAssignment, OfficePolicy (+28 more)

### Community 7 - "update"
Cohesion: 0.02
Nodes (154): add(), addChunk(), addEventListener(), addInfoPane(), addInner(), addRange(), addWindowListeners(), adjust() (+146 more)

### Community 8 - "markdown-editor.js"
Cohesion: 0.03
Nodes (137): AX(), combine(), readMeasure(), To(), tQ(), xe(), z$(), Aa() (+129 more)

### Community 9 - "stat/chart.js"
Cohesion: 0.03
Nodes (88): active(), _animateOptions(), applyStack(), beforeDatasetDraw(), beforeDatasetsDraw(), beforeDraw(), br(), Cn() (+80 more)

### Community 10 - "resolve"
Cohesion: 0.06
Nodes (135): addKeyboardShortcuts(), after(), ag(), al(), allowedMarks(), before(), blockRange(), Bs() (+127 more)

### Community 11 - "Illuminate\Database\Eloquent\Relations\BelongsTo"
Cohesion: 0.02
Nodes (28): ApprovalHistory, LogOptions, LogOptions, AssignmentPermissionOverride, LogOptions, AssignmentScope, LogOptions, GoodsReceiptItem (+20 more)

### Community 12 - "prop"
Cohesion: 0.06
Nodes (69): AQ(), atLastNode(), au(), child(), childAfter(), childBefore(), cursor(), cursorAt() (+61 more)

### Community 13 - "ApproverMapping"
Cohesion: 0.04
Nodes (16): ApproverDelegation, ApproverMapping, Workflow, WorkflowStep, WorkflowVersion, ApproverDelegationPolicy, WorkflowPolicy, Carbon (+8 more)

### Community 14 - "fromObject"
Cohesion: 0.03
Nodes (115): im(), ae(), after(), Ag(), Am(), before(), buildFormatParser(), C() (+107 more)

### Community 15 - "Illuminate\Database\Eloquent\Model"
Cohesion: 0.03
Nodes (13): Attachment, Invoice, Payment, WorkflowCondition, AttachmentPolicy, ContextPolicy, InvoicePolicy, InvoicePaymentService (+5 more)

### Community 16 - ".slice"
Cohesion: 0.04
Nodes (100): accepts(), addAttributes(), addInner(), addMaps(), addOptions(), addStep(), addTransform(), af() (+92 more)

### Community 17 - "_update"
Cohesion: 0.03
Nodes (119): addElements(), afterBuildTicks(), afterCalculateLabelRotation(), afterDataLimits(), afterDatasetsUpdate(), afterFit(), afterSetDimensions(), afterTickToLabelConversion() (+111 more)

### Community 18 - "Vendor"
Cohesion: 0.03
Nodes (21): DepartureBatchExporter, ProcurementCategoryExporter, ProcurementItemExporter, ProcurementUnitExporter, ProcurementVariantExporter, UserAssignmentExporter, VendorExporter, BackedEnum (+13 more)

### Community 19 - "ProcurementItem"
Cohesion: 0.03
Nodes (17): DepartureBatch, ProcurementItem, ProcurementUnit, ProcurementVariant, ApproverMappingPolicy, ProcurementItemPolicy, ProcurementUnitPolicy, ProcurementVariantPolicy (+9 more)

### Community 20 - "get"
Cohesion: 0.06
Nodes (60): addBlockWidget(), addBreak(), addComposition(), addDelimiter(), addInlineWidget(), addLine(), addLineStart(), addLineStartIfNotCovered() (+52 more)

### Community 21 - "o"
Cohesion: 0.04
Nodes (95): o(), _0(), addGlobalAttributes(), addInputRules(), addMark(), addPasteRules(), addStoredMark(), Ah() (+87 more)

### Community 22 - "InvoiceResource"
Cohesion: 0.03
Nodes (33): ListApprovalInbox, ViewApprovalInbox, CreateApproverDelegation, EditApproverDelegation, ListApproverDelegations, CreateApproverMapping, EditApproverMapping, ListApproverMappings (+25 more)

### Community 23 - "e"
Cohesion: 0.06
Nodes (88): Ac(), add(), addCommands(), addNodeView(), addProseMirrorPlugins(), AS(), Bf(), Bm() (+80 more)

### Community 24 - "by"
Cohesion: 0.03
Nodes (103): Ad(), addExtensions(), au(), ay(), Bd(), Bg(), by(), cl() (+95 more)

### Community 25 - "i"
Cohesion: 0.07
Nodes (87): B(), cP(), hd(), Nh(), wo(), zf(), i(), m() (+79 more)

### Community 26 - "ProcurementCategory"
Cohesion: 0.03
Nodes (17): BackedEnum, UnitEnum, PurchaseRequestResource, ProcurementCategory, PurchaseRequestFieldValue, ProcurementCategoryPolicy, PurchaseRequestFieldValueFactory, Illuminate\Database\Eloquent\Relations\HasOne (+9 more)

### Community 27 - "draw"
Cohesion: 0.04
Nodes (122): addBox(), adjustHitBoxes(), B(), _calculateBarValuePixels(), calculateCircumference(), calculateLabelRotation(), _calculatePadding(), _circumference() (+114 more)

### Community 28 - "create"
Cohesion: 0.05
Nodes (62): clone(), create(), Ct(), dtFormatter(), Ec(), eras(), expandFormat(), extract() (+54 more)

### Community 29 - "Illuminate\Database\Eloquent\Factories\Factory"
Cohesion: 0.03
Nodes (33): ApprovalHistoryFactory, ApprovalInstanceStepFactory, ApproverDelegationFactory, ApproverMappingFactory, BranchFactory, CostCenterFactory, DepartmentFactory, DepartureBatchFactory (+25 more)

### Community 30 - "t"
Cohesion: 0.11
Nodes (78): compute(), flatten(), from(), le(), node(), t(), at(), Be() (+70 more)

### Community 31 - "file-upload.js"
Cohesion: 0.05
Nodes (60): hc(), me(), Bp(), c(), ca(), Ce(), clickPercent(), cm() (+52 more)

### Community 32 - "slice"
Cohesion: 0.04
Nodes (79): addChild(), addGaps(), addLeafElement(), addNode(), advance(), balance(), break(), _c() (+71 more)

### Community 33 - "lineAt"
Cohesion: 0.05
Nodes (50): addElement(), applyChanges(), balanced(), baseIndent(), baseIndentFor(), blank(), blockAt(), column() (+42 more)

### Community 34 - "tables.js"
Cohesion: 0.09
Nodes (64): A(), ae(), areRecordsPartiallySelected(), areRecordsSelected(), areRecordsToggleable(), B(), be(), C() (+56 more)

### Community 35 - "E"
Cohesion: 0.07
Nodes (33): addEventListener(), aspectRatio(), au(), bindResponsiveEvents(), _calculateBarIndexPixels(), E(), Eo(), eu() (+25 more)

### Community 36 - "parse"
Cohesion: 0.05
Nodes (69): aa(), af(), afterAutoSkip(), ah(), at(), br(), buildLookupTable(), buildOrUpdateScales() (+61 more)

### Community 37 - "toString"
Cohesion: 0.12
Nodes (22): addToSet(), bd(), Bh(), childString(), clearDelayedAndroidKey(), delayAndroidKey(), flushIOSKey(), forceFlush() (+14 more)

### Community 38 - "facet"
Cohesion: 0.04
Nodes (83): aa(), accept(), active(), baseTheme(), Bg(), blur(), bu(), build() (+75 more)

### Community 39 - "eq"
Cohesion: 0.06
Nodes (54): addNode(), ao(), append(), bt(), Cc(), co(), Cr(), dd() (+46 more)

### Community 40 - "go"
Cohesion: 0.07
Nodes (41): th(), alpha(), ao(), ba(), be(), co(), color(), darken() (+33 more)

### Community 41 - "columns/select.js"
Cohesion: 0.07
Nodes (42): A(), addBadgesForSelectedOptions(), addSingleBadge(), addSingleSelectionDisplay(), An(), b(), Bt(), Cn() (+34 more)

### Community 42 - "add"
Cohesion: 0.07
Nodes (36): active(), add(), _animateOptions(), average(), bh(), bi(), Bo(), Ch() (+28 more)

### Community 43 - "PurchaseOrder"
Cohesion: 0.07
Nodes (3): InvoiceForm, PurchaseOrder, ReceivingService

### Community 44 - "notifications.js"
Cohesion: 0.06
Nodes (31): actions(), button(), c(), close(), configureAnimations(), configureTransitions(), constructor(), danger() (+23 more)

### Community 45 - "support.js"
Cohesion: 0.06
Nodes (41): acquireScrollLock(), close(), closeQuietly(), commit(), destroy(), distribute(), es(), Fa() (+33 more)

### Community 46 - "constructor"
Cohesion: 0.06
Nodes (40): apply(), bd(), bg(), chartOptionScopes(), constructor(), contains(), Cs(), _d() (+32 more)

### Community 47 - "u"
Cohesion: 0.15
Nodes (44): fromJSON(), wa(), yf(), yS(), d(), g(), b(), $c() (+36 more)

### Community 48 - "slider.js"
Cohesion: 0.08
Nodes (36): e(), r(), s(), Ae(), ar(), Be(), Bt(), De() (+28 more)

### Community 49 - "reduce"
Cohesion: 0.04
Nodes (78): addActions(), advanceFully(), advanceStack(), allActions(), allows(), apply(), attrs(), b0() (+70 more)

### Community 50 - "te"
Cohesion: 0.05
Nodes (7): Bn(), ji(), Ri(), te(), Vi(), Xc(), Yc()

### Community 51 - "create"
Cohesion: 0.07
Nodes (44): addChanges(), addSelection(), Ah(), applyTransaction(), asSingle(), changeByRange(), changes(), compose() (+36 more)

### Community 52 - "Ye"
Cohesion: 0.10
Nodes (43): Rd(), $a(), at(), bk(), bp(), Dk(), dp(), Ek() (+35 more)

### Community 53 - "getContext"
Cohesion: 0.05
Nodes (72): acquireContext(), bl(), buildTicks(), Ca(), calculateLabelRotation(), _calculatePadding(), ci(), Cl() (+64 more)

### Community 54 - "parse"
Cohesion: 0.08
Nodes (42): addAll(), addDOM(), addElement(), addElementByRule(), addTextNode(), addToSet(), allowsMarkType(), closeExtra() (+34 more)

### Community 55 - "find"
Cohesion: 0.07
Nodes (46): activateHover(), baseDirAt(), bidiIn(), bidiSpans(), bidiSpansAt(), bP(), cd(), checkHover() (+38 more)

### Community 56 - "constructor"
Cohesion: 0.06
Nodes (41): applyInitialSize(), Bo(), chain(), constructor(), createCommandManager(), createContainer(), createDoc(), createExtensionManager() (+33 more)

### Community 57 - "I"
Cohesion: 0.08
Nodes (39): ad(), bs(), Ci(), createResolver(), describe(), dh(), Di(), ed() (+31 more)

### Community 58 - "BudgetReservationService"
Cohesion: 0.14
Nodes (5): Budget, LogOptions, BudgetReservation, LogOptions, BudgetReservationService

### Community 59 - "draw"
Cohesion: 0.08
Nodes (42): addElements(), Ae(), bi(), bindEvents(), bindUserEvents(), buildOrUpdateScales(), _checkEventBindings(), clear() (+34 more)

### Community 60 - "oi"
Cohesion: 0.07
Nodes (43): alpha(), co(), color(), darken(), desaturate(), fo(), Gc(), greyscale() (+35 more)

### Community 61 - "ValidationException"
Cohesion: 0.13
Nodes (4): self, SampleShipmentService, SampleShipmentStatus, ValidationException

### Community 62 - "jt"
Cohesion: 0.13
Nodes (38): At(), bi(), bn(), ci(), ct(), de(), di(), En() (+30 more)

### Community 63 - "jh"
Cohesion: 0.11
Nodes (23): Z(), acquireContext(), bu(), dataset(), eg(), Ft(), getRange(), Ii() (+15 more)

### Community 64 - "components/select.js"
Cohesion: 0.10
Nodes (30): An(), b(), Cn(), D(), Dn(), dt(), Et(), getLabelsForMultipleSelection() (+22 more)

### Community 65 - "_update"
Cohesion: 0.06
Nodes (54): Image(), afterBuildTicks(), afterCalculateLabelRotation(), afterDataLimits(), afterDatasetsUpdate(), afterFit(), afterSetDimensions(), afterTickToLabelConversion() (+46 more)

### Community 66 - "fn"
Cohesion: 0.12
Nodes (35): aa(), ba(), cr(), da(), de(), dt(), ei(), Fi() (+27 more)

### Community 67 - "parse"
Cohesion: 0.08
Nodes (37): En(), al(), buildOrUpdateElements(), determineDataLimits(), en(), endOf(), formats(), getAllParsedValues() (+29 more)

### Community 68 - "Cn"
Cohesion: 0.17
Nodes (32): _a(), Bi(), br(), Bt(), ca(), Cn(), ct(), Dn() (+24 more)

### Community 69 - "UmrahBatch"
Cohesion: 0.08
Nodes (4): DistributionForm, UmrahBatch, UmrahBatchPolicy, BatchPilgrimTest

### Community 70 - "r"
Cohesion: 0.20
Nodes (31): ai(), ar(), c(), d(), di(), g(), Hi(), Hn() (+23 more)

### Community 71 - "addEventListener"
Cohesion: 0.15
Nodes (19): Hn(), addEventListener(), bindResponsiveEvents(), isAttached(), ja(), jn(), ke(), ne() (+11 more)

### Community 72 - "Distribution"
Cohesion: 0.08
Nodes (5): DistributionResource, UnitEnum, DistributionInfolist, Distribution, DistributionPolicy

### Community 73 - "echo.js"
Cohesion: 0.09
Nodes (15): a(), ar(), at(), cr(), d(), f(), H(), ji() (+7 more)

### Community 74 - "Xt"
Cohesion: 0.16
Nodes (30): ae(), At(), bi(), bn(), ci(), ct(), di(), Dt() (+22 more)

### Community 75 - "DistributionItem"
Cohesion: 0.10
Nodes (3): DistributionItem, PilgrimDistributionItem, PilgrimDistributionItemPolicy

### Community 77 - "Pilgrim"
Cohesion: 0.11
Nodes (3): Pilgrim, PilgrimPolicy, PilgrimDistributionTest

### Community 79 - "A"
Cohesion: 0.13
Nodes (22): A(), ar(), aspectRatio(), Bt(), _computeLabelSizes(), drawTitle(), ee(), fn() (+14 more)

### Community 80 - "ApprovalInstanceStep"
Cohesion: 0.19
Nodes (3): ApprovalInstanceStep, ApprovalActionService, Carbon

### Community 82 - "g$"
Cohesion: 0.09
Nodes (34): acceptToken(), between(), c0(), d0(), De(), Dg(), E$(), eh() (+26 more)

### Community 83 - "_each"
Cohesion: 0.11
Nodes (22): addControllers(), addPlugins(), addScales(), cancel(), _createDescriptors(), _descriptors(), _each(), _exec() (+14 more)

### Community 86 - "W"
Cohesion: 0.06
Nodes (49): aO(), b1(), charCategorizer(), Fc(), findRegions(), getCursor(), getDeco(), getSkippingParser() (+41 more)

### Community 87 - "S"
Cohesion: 0.13
Nodes (20): aa(), an(), apply(), fi(), getPadding(), ii(), It(), ji() (+12 more)

### Community 88 - "WorkflowResource"
Cohesion: 0.09
Nodes (10): BackedEnum, UnitEnum, WorkflowResource, BackedEnum, UnitEnum, WorkflowStepResource, BackedEnum, UnitEnum (+2 more)

### Community 89 - "Illuminate\Database\Eloquent\Relations\HasMany"
Cohesion: 0.03
Nodes (9): Branch, CostCenter, Department, BranchPolicy, CostCenterPolicy, DepartmentPolicy, BudgetFactory, Illuminate\Database\Eloquent\Relations\HasMany (+1 more)

### Community 91 - "package.json"
Cohesion: 0.08
Nodes (23): concurrently, @laravel/multiplex, laravel-vite-plugin, devDependencies, concurrently, laravel-vite-plugin, tailwindcss, @tailwindcss/vite (+15 more)

### Community 92 - "eq"
Cohesion: 0.05
Nodes (54): activeForPoint(), addActive(), addBlock(), addLineDeco(), Ar(), as(), be(), blankContent() (+46 more)

### Community 93 - "ls"
Cohesion: 0.12
Nodes (24): ae(), Ao(), as(), cs(), Ee(), Ge(), he(), Io() (+16 more)

### Community 94 - "ar"
Cohesion: 0.08
Nodes (32): applyStack(), ar(), Ba(), Be(), beforeDatasetDraw(), beforeDatasetsDraw(), beforeDraw(), dc() (+24 more)

### Community 95 - "Cs"
Cohesion: 0.09
Nodes (24): afterAutoSkip(), bs(), buildLookupTable(), Cs(), Dn(), ea(), first(), getDecimalForValue() (+16 more)

### Community 96 - "ProcurementCategoryConfiguration"
Cohesion: 0.10
Nodes (4): BackedEnum, UnitEnum, ProcurementCategoryResource, ProcurementCategoryConfiguration

### Community 98 - "ProcurementField"
Cohesion: 0.11
Nodes (3): ProcurementField, self, DynamicFieldValidationTest

### Community 99 - "En"
Cohesion: 0.13
Nodes (20): Ra(), apply(), At(), B(), En(), fs(), go(), Hr() (+12 more)

### Community 100 - "ApprovalTaskLifecycleService"
Cohesion: 0.17
Nodes (7): ProcessApprovalSlaJob, ApprovalInstanceCreator, ApprovalTaskLifecycleService, Carbon, Illuminate\Contracts\Queue\ShouldBeUnique, Illuminate\Contracts\Queue\ShouldQueue, Illuminate\Foundation\Queue\Queueable

### Community 101 - "color-picker.js"
Cohesion: 0.11
Nodes (8): constructor(), [g](), style(), update(), [x](), lx(), ta(), tt()

### Community 102 - "toString"
Cohesion: 0.17
Nodes (16): allowsMarks(), checkContent(), endIndex(), getObj(), hasProtocol(), Rc(), render(), startIndex() (+8 more)

### Community 103 - "constructor"
Cohesion: 0.06
Nodes (51): add(), bn(), bo(), _cachedScopes(), chartOptionScopes(), configure(), constructor(), createResolver() (+43 more)

### Community 104 - "PilgrimImporter"
Cohesion: 0.16
Nodes (3): PilgrimImporter, UmrahBatchImporter, Filament\Actions\Imports\Models\Import

### Community 105 - "filament/app.js"
Cohesion: 0.14
Nodes (8): B(), close(), G(), init(), P(), setUpResizeObserver(), x(), Y()

### Community 106 - "ul"
Cohesion: 0.29
Nodes (8): ai(), _e(), isPointInArea(), li(), ra(), ul(), xs(), Z()

### Community 107 - "ApprovalTaskAssigned"
Cohesion: 0.18
Nodes (5): ApprovalTaskAssigned, ApprovalTaskEscalated, ApprovalTaskSlaWarning, Illuminate\Bus\Queueable, Illuminate\Notifications\Notification

### Community 108 - "CategoryRelationGuard"
Cohesion: 0.22
Nodes (3): CategoryRelationGuard, self, CategoryRelationGuardsTest

### Community 109 - "closeDropdown"
Cohesion: 0.23
Nodes (17): applyDisabledState(), closeDropdown(), constructor(), destroy(), disable(), enable(), focusNextOption(), focusPreviousOption() (+9 more)

### Community 110 - "closeDropdown"
Cohesion: 0.23
Nodes (17): applyDisabledState(), closeDropdown(), constructor(), destroy(), disable(), enable(), focusNextOption(), focusPreviousOption() (+9 more)

### Community 111 - "fn"
Cohesion: 0.24
Nodes (17): Ce(), ei(), fn(), Ft(), Ie(), Le(), ln(), ni() (+9 more)

### Community 113 - "Login.php"
Cohesion: 0.28
Nodes (4): Login, Filament\Auth\Pages\Login, Filament\Schemas\Components\Component, Illuminate\Contracts\Support\Htmlable

### Community 114 - "HealthController.php"
Cohesion: 0.18
Nodes (6): AttachmentDownloadController, Controller, HealthController, Illuminate\Http\JsonResponse, Illuminate\Http\Response, Symfony\Component\HttpFoundation\StreamedResponse

### Community 115 - "KeycloakController.php"
Cohesion: 0.19
Nodes (3): KeycloakClient, KeycloakConfig, self

### Community 116 - "Closure"
Cohesion: 0.23
Nodes (7): EnsureAccessContext, Response, EnsureActiveOffice, Response, RequireApplicationAssignment, Closure, Symfony\Component\HttpFoundation\Response

### Community 117 - "scripts"
Cohesion: 0.13
Nodes (15): scripts, dev, post-autoload-dump, post-update-cmd, pre-package-uninstall, test, Composer\\Config::disableProcessTimeout, Illuminate\\Foundation\\ComposerScripts::postAutoloadDump (+7 more)

### Community 118 - "fn"
Cohesion: 0.27
Nodes (15): Ce(), ei(), fn(), Ie(), Kt(), Le(), ln(), ni() (+7 more)

### Community 119 - "ApprovalInstance"
Cohesion: 0.13
Nodes (3): ApprovalInstance, Illuminate\Database\Eloquent\Relations\HasManyThrough, ApprovalSlaTest

### Community 120 - "composer.json"
Cohesion: 0.14
Nodes (13): autoload-dev, psr-4, description, keywords, license, minimum-stability, name, prefer-stable (+5 more)

### Community 121 - "Filament\Resources\Pages\ManageRecords"
Cohesion: 0.21
Nodes (5): ManageDepartureBatches, ManageProcurementItems, ManageWorkflows, ManagePurchaseRequests, Filament\Resources\Pages\ManageRecords

### Community 122 - "Illuminate\Database\Migrations\Migration"
Cohesion: 0.19
Nodes (4): CreateActivityLogTable, AddEventColumnToActivityLogTable, AddBatchUuidColumnToActivityLogTable, Illuminate\Database\Migrations\Migration

### Community 123 - "t"
Cohesion: 0.18
Nodes (12): Ce(), De(), di(), e(), Ht(), Ie(), Re(), t() (+4 more)

### Community 124 - "selectOption"
Cohesion: 0.24
Nodes (13): addBadgesForSelectedOptions(), addSingleBadge(), addSingleSelectionDisplay(), bt(), createBadgeElement(), createRemoveButton(), getLabelForSingleSelection(), getSelectedOptionLabel() (+5 more)

### Community 125 - "renderOptions"
Cohesion: 0.37
Nodes (13): createOptionElement(), deferPositionDropdown(), filterOptions(), handleSearch(), hideLoadingState(), openDropdown(), populateLabelRepositoryFromOptions(), positionDropdown() (+5 more)

### Community 126 - "renderOptions"
Cohesion: 0.37
Nodes (13): createOptionElement(), deferPositionDropdown(), filterOptions(), handleSearch(), hideLoadingState(), openDropdown(), populateLabelRepositoryFromOptions(), positionDropdown() (+5 more)

### Community 127 - "AdminPanelProvider.php"
Cohesion: 0.47
Nodes (3): AdminPanelProvider, Filament\Panel, Filament\PanelProvider

### Community 129 - "r"
Cohesion: 0.18
Nodes (12): Be(), ei(), ii(), le(), ni(), oi(), r(), ri() (+4 more)

### Community 133 - "DatePicker"
Cohesion: 0.33
Nodes (3): DynamicFieldSchema, DatePicker, Filament\Forms\Components\Field

### Community 135 - "Illuminate\Http\Request"
Cohesion: 0.42
Nodes (4): KeycloakController, OfficeContextController, Illuminate\Http\RedirectResponse, Illuminate\Http\Request

### Community 137 - "lt"
Cohesion: 0.33
Nodes (11): de(), Et(), gi(), gn(), ii(), Kt(), lt(), mn() (+3 more)

### Community 140 - "command"
Cohesion: 0.20
Nodes (9): command, enabled, type, mcp, laravel-boost, $schema, artisan, boost:mcp (+1 more)

### Community 141 - "actions/actions.js"
Cohesion: 0.44
Nodes (8): closeModal(), generateModalId(), getActionNestingIndexFromModalId(), init(), openModal(), rememberPreviouslyFocusedElement(), restorePreviouslyFocusedElement(), syncActionModals()

### Community 145 - "require-dev"
Cohesion: 0.22
Nodes (9): require-dev, fakerphp/faker, laravel/boost, laravel/pail, laravel/pao, laravel/pint, mockery/mockery, nunomaduro/collision (+1 more)

### Community 146 - "ProcurementFieldResource"
Cohesion: 0.32
Nodes (4): BackedEnum, UnitEnum, ProcurementFieldResource, ProcurementFieldType

### Community 147 - "RedactSensitiveData"
Cohesion: 0.39
Nodes (3): RedactSensitiveData, Monolog\LogRecord, Monolog\Processor\ProcessorInterface

### Community 148 - "setup"
Cohesion: 0.25
Nodes (8): post-root-package-install, setup, composer install, npm install --ignore-scripts, npm run build, @php artisan key:generate, @php artisan migrate --force, @php -r \"file_exists('.env') || copy('.env.example', '.env');\

### Community 150 - "Illuminate\Console\Command"
Cohesion: 0.38
Nodes (3): ProcessApprovalSla, ValidateEnvironment, Illuminate\Console\Command

### Community 152 - "config"
Cohesion: 0.29
Nodes (7): pestphp/pest-plugin, php-http/discovery, config, allow-plugins, optimize-autoloader, preferred-install, sort-packages

### Community 153 - "require"
Cohesion: 0.29
Nodes (7): require, bezhansalleh/filament-shield, filament/filament, laravel/framework, laravel/tinker, mradder/filament-logger, php

### Community 155 - "nr"
Cohesion: 0.29
Nodes (7): Dt(), Fe(), He(), ir(), Mt(), nr(), rt()

### Community 159 - "i"
Cohesion: 0.40
Nodes (6): E(), w(), b(), g(), i(), P()

### Community 164 - "psr-4"
Cohesion: 0.40
Nodes (5): autoload, psr-4, App\\, Database\\Factories\\, Database\\Seeders\\

### Community 165 - "ot"
Cohesion: 0.50
Nodes (5): a$(), _h(), O$(), ot(), r$()

### Community 176 - "post-create-project-cmd"
Cohesion: 0.50
Nodes (4): post-create-project-cmd, @php artisan key:generate --ansi, @php artisan migrate --graceful --ansi, @php -r \"file_exists('database/database.sqlite') || touch('database/database.sqlite');\

### Community 177 - "2026_08_29_191404_add_filament_logger_indexes_to_activity_log_table.php"
Cohesion: 0.83
Nodes (3): down(), tableName(), up()

### Community 185 - "extra"
Cohesion: 0.67
Nodes (3): extra, laravel, dont-discover

## Knowledge Gaps
- **71 isolated node(s):** `php`, `php`, `$schema`, `name`, `type` (+66 more)
  These have ≤1 connection - possible missing edges or undocumented components.
- **45 thin communities (<3 nodes) omitted from report** — run `graphify query` to explore isolated nodes.

## Suggested Questions
_Questions this graph is uniquely positioned to answer:_

- **Why does `User` connect `User` to `.receivableOrder`, `Illuminate\Database\Eloquent\Builder`, `.workflow`, `KeycloakOidcCallbackTest`, `TestCase`, `Office`, `ProcurementFieldPolicy`, `Illuminate\Database\Eloquent\Relations\BelongsTo`, `ApproverMapping`, `Illuminate\Database\Eloquent\Model`, `BudgetPolicy`, `Vendor`, `ProcurementItem`, `InvoiceResource`, `GoodsReceiptsRelationManager`, `ProcurementCategory`, `Illuminate\Database\Eloquent\Factories\Factory`, `.financeTask`, `FinanceApprovalDetail`, `PurchaseOrder`, `KeycloakUserProvisioner`, `BudgetReservationService`, `ValidationException`, `UmrahBatch`, `Distribution`, `DistributionItem`, `InvoiceMatchingService`, `Pilgrim`, `DistributionService`, `ApprovalInstanceStep`, `SampleShipmentReceiptService`, `SampleShipment`, `ProcurementReviewService`, `Illuminate\Database\Eloquent\Relations\HasMany`, `AttachmentService`, `GoodsReceipt`, `ApprovalTaskLifecycleService`, `ApprovalInstance`?**
  _High betweenness centrality (0.039) - this node is a cross-community bridge._
- **Why does `o()` connect `o` to `rich-editor.js`, `r`, `code-editor.js`, `components/chart.js`, `update`, `lt`, `resolve`, `stat/chart.js`, `prop`, `fromObject`, `.slice`, `_update`, `get`, `e`, `by`, `i`, `nr`, `create`, `draw`, `t`, `i`, `slice`, `lineAt`, `file-upload.js`, `tables.js`, `E`, `toString`, `facet`, `eq`, `parse`, `columns/select.js`, `add`, `notifications.js`, `constructor`, `u`, `slider.js`, `create`, `getContext`, `parse`, `find`, `constructor`, `I`, `draw`, `jt`, `jh`, `components/select.js`, `_update`, `parse`, `Cn`, `r`, `addEventListener`, `Xt`, `A`, `_each`, `W`, `S`, `eq`, `ls`, `ar`, `Cs`, `constructor`, `ul`, `fn`, `t`?**
  _High betweenness centrality (0.035) - this node is a cross-community bridge._
- **Why does `u()` connect `u` to `update`, `markdown-editor.js`, `stat/chart.js`, `resolve`, `prop`, `fromObject`, `.slice`, `o`, `e`, `by`, `i`, `draw`, `t`, `i`, `lineAt`, `tables.js`, `parse`, `toString`, `facet`, `eq`, `columns/select.js`, `add`, `support.js`, `constructor`, `slider.js`, `reduce`, `getContext`, `I`, `jh`, `components/select.js`, `_update`, `fn`, `parse`, `r`, `A`, `ls`, `Cs`, `constructor`, `t`?**
  _High betweenness centrality (0.023) - this node is a cross-community bridge._
- **Are the 112 inferred relationships involving `User` (e.g. with `.receiverOptions()` and `.usersForOffice()`) actually correct?**
  _`User` has 112 INFERRED edges - model-reasoned connections that need verification._
- **Are the 60 inferred relationships involving `PurchaseRequest` (e.g. with `.handleRecordCreation()` and `.getEloquentQuery()`) actually correct?**
  _`PurchaseRequest` has 60 INFERRED edges - model-reasoned connections that need verification._
- **Are the 176 inferred relationships involving `o()` (e.g. with `Be()` and `i()`) actually correct?**
  _`o()` has 176 INFERRED edges - model-reasoned connections that need verification._
- **What connects `php`, `php`, `$schema` to the rest of the system?**
  _71 weakly-connected nodes found - possible documentation gaps or missing edges._