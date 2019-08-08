#!groovy

@Library('PipelineUtilities') _
resourceManager.requestResources(role: "BLD", quantity: 1, isSingleUse: false)

node("BLD") {
	timestamps {
		// Git Checkout of Library
		stage('Checkout') {
			deleteDir()
			bat '''MKDIR %WORKSPACE%\\incoming'''
			dir('incoming') {
				checkout scm
			}
		}

		stage('Bootstrap') {
			//This will init/pull down the BuildTools submodule, which includes the Jenkinsfile
			//It is up to the project's bootstrap to do a submodule "init" (specific version) or "remote" (latest version) or specify a branch.
			bat '''git clone "https://git-stash.mattersight.local/scm/portal/portal-pipeline_utilities.git" .build\\portal-pipeline_utilities'''

			pipeline = load '.\\.build\\portal-pipeline_utilities\\resources\\JenkinsLibrary.groovy'
			pipeline.runJenkinsfile()
		}
	}
}