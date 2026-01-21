# Migration Plan: Switch from wkhtmltopdf (Snappy) to dompdf for PDF Export

## Overview
The current PDF export functionality uses `barryvdh/laravel-snappy` with wkhtmltopdf binary. Due to compatibility issues on Linux and Docker environments (where wkhtmltopdf binary may not be available or properly configured), we propose migrating to `barryvdh/laravel-dompdf`, a pure PHP solution that doesn't require external binaries.

## Current State
- **Package**: `barryvdh/laravel-snappy`
- **Dependency**: wkhtmltopdf binary (external tool)
- **Usage**: `SnappyPdf::loadView()` in `ExportService::exportToPdf()`
- **Issues**: Binary compatibility on Linux/Docker, installation complexity, potential security concerns with external binaries

## Proposed Solution
- **Package**: `barryvdh/laravel-dompdf`
- **Dependency**: Pure PHP (no external binaries)
- **Usage**: `PDF::loadView()` in `ExportService::exportToPdf()`
- **Benefits**: Better compatibility, easier deployment, no system dependencies

## Pros and Cons Analysis

### Pros of Switching to dompdf
1. **Compatibility**:
   - Works on any PHP environment without external dependencies
   - No need to install wkhtmltopdf binary on servers/Docker containers
   - Consistent behavior across development, staging, and production

2. **Deployment Simplicity**:
   - No additional system packages to install during deployment
   - Works out-of-the-box in Docker containers
   - Reduces CI/CD complexity

3. **Security**:
   - No external binary execution (reduces potential security risks)
   - All processing happens within PHP sandbox

4. **Maintenance**:
   - Fewer moving parts to maintain
   - No need to update binary versions separately
   - Better long-term support (dompdf is actively maintained)

5. **Performance**:
   - Potentially faster startup (no process spawning)
   - Lower memory overhead in some cases

### Cons of Switching to dompdf
1. **Rendering Quality**:
   - dompdf has different rendering engine than WebKit (wkhtmltopdf)
   - May require CSS adjustments for complex layouts
   - Some advanced CSS features might not be supported

2. **Performance**:
   - Can be slower for very large/complex documents
   - Higher memory usage for large PDFs
   - No parallel processing options

3. **Feature Limitations**:
   - Limited JavaScript support (dompdf doesn't execute JS)
   - Fewer customization options for PDF generation
   - No built-in header/footer pagination like wkhtmltopdf

4. **Migration Effort**:
   - May require PDF template adjustments
   - Need to test all PDF outputs for visual consistency
   - Potential need for CSS workarounds

5. **Font Handling**:
   - Different font embedding approach
   - May require font configuration changes

## Migration Steps

### Phase 1: Preparation
1. **Install dompdf package**:
   ```bash
   composer require barryvdh/laravel-dompdf
   ```

2. **Backup current PDF templates**:
   - Identify all PDF view templates
   - Create backups for rollback

3. **Test current PDFs**:
   - Generate sample PDFs with current setup
   - Document visual output for comparison

### Phase 2: Code Changes
1. **Update ExportService**:
   - Change import from `Barryvdh\Snappy\Facades\SnappyPdf` to `Barryvdh\DomPDF\Facade as PDF`
   - Replace `SnappyPdf::loadView()` with `PDF::loadView()`
   - Adjust any wkhtmltopdf-specific options

2. **Update PDF Views**:
   - Review and adjust CSS for dompdf compatibility
   - Test font rendering
   - Adjust layouts if needed

3. **Configuration**:
   - Publish dompdf config if needed: `php artisan vendor:publish --provider="Barryvdh\DomPDF\ServiceProvider"`
   - Configure paper size, orientation, etc.

### Phase 3: Testing
1. **Unit Tests**:
   - Update any PDF-related tests
   - Test PDF generation methods

2. **Visual Testing**:
   - Compare generated PDFs side-by-side
   - Test different report types (income statement, balance sheet, etc.)
   - Verify on different environments

3. **Performance Testing**:
   - Measure generation time
   - Check memory usage
   - Test with large datasets

### Phase 4: Deployment
1. **Gradual Rollout**:
   - Deploy to staging first
   - Test in production-like environment

2. **Monitoring**:
   - Monitor PDF generation performance
   - Watch for user reports of PDF issues

3. **Rollback Plan**:
   - Keep wkhtmltopdf package installed during transition
   - Have quick rollback option if issues arise

## Risk Assessment

### High Risk
- PDF visual quality degradation
- Performance issues with large reports
- CSS compatibility problems

### Medium Risk
- Font rendering differences
- Layout adjustments needed
- Learning curve for dompdf-specific features

### Low Risk
- Package installation
- Basic functionality (should work similarly)
- Deployment compatibility

## Alternatives Considered
1. **Keep wkhtmltopdf**: Install binary in Docker images, but increases complexity
2. **Use puppeteer**: Modern alternative, but requires Node.js and more complexity
3. **TCPDF/mPDF**: Other PHP PDF libraries, similar pros/cons to dompdf
4. **Hybrid approach**: Use dompdf for simple PDFs, wkhtmltopdf for complex ones

## Recommendation
**Proceed with migration** to dompdf for better long-term maintainability and deployment simplicity, especially since this is a forked project where deployment consistency is crucial. The cons are manageable with proper testing and CSS adjustments.

## Timeline Estimate
- **Preparation**: 1-2 days
- **Implementation**: 2-3 days
- **Testing**: 2-3 days
- **Deployment**: 1 day
- **Total**: 1 week

## Success Criteria
- All PDF exports work without errors
- Visual quality maintained or improved
- Performance acceptable (generation < 30 seconds for typical reports)
- No external dependencies required
- Works in Docker/Linux environments

## Next Steps
1. Review this plan with team
2. Get approval to proceed
3. Schedule implementation during low-traffic period
4. Assign developer for migration
5. Set up testing environment

Would you like me to elaborate on any specific aspect of this plan?