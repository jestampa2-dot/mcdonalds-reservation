const { withAppBuildGradle } = require('@expo/config-plugins');

const signingCompatibilityBlock = `

// Keep direct-install APKs compatible with Android and manufacturer package installers.
androidComponents {
    onVariants(selector().all()) { variant ->
        if (variant.buildType == "release" && variant.signingConfig != null) {
            variant.signingConfig.enableV1Signing.set(true)
            variant.signingConfig.enableV2Signing.set(true)
            variant.signingConfig.enableV3Signing.set(true)
        }
    }
}
`;

module.exports = function withCompatibleApkSigning(config) {
  return withAppBuildGradle(config, (gradleConfig) => {
    if (gradleConfig.modResults.language !== 'groovy') {
      throw new Error('Compatible APK signing requires a Groovy app build.gradle file.');
    }

    if (!gradleConfig.modResults.contents.includes('enableV1Signing.set(true)')) {
      gradleConfig.modResults.contents += signingCompatibilityBlock;
    }

    return gradleConfig;
  });
};
