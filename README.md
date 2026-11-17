<div align="center">

# Cluster Monitoring Using Metrics Server and Kubernetes Dashboard

**An experimental study of pod and node resource usage on a Minikube cluster, measured with kubectl top and the Kubernetes Dashboard under generated load**

![Ubuntu](https://img.shields.io/badge/Ubuntu-24.04-E95420?logo=ubuntu&logoColor=white)
![Docker](https://img.shields.io/badge/Docker-Container-2496ED?logo=docker&logoColor=white)
![Kubernetes](https://img.shields.io/badge/Kubernetes-v1.37-326CE5?logo=kubernetes&logoColor=white)
![Minikube](https://img.shields.io/badge/Minikube-v1.39-F7B93E?logo=kubernetes&logoColor=white)
![PHP](https://img.shields.io/badge/PHP-8.3-777BB4?logo=php&logoColor=white)
![Apache](https://img.shields.io/badge/Apache-Web_Server-D22128?logo=apache&logoColor=white)
![Metrics Server](https://img.shields.io/badge/Metrics_Server-v0.9.0-326CE5?logo=kubernetes&logoColor=white)

**KLE Technological University | Cloud Computing Lab Evaluation 2 | Kubernetes and Microservices Deployment | Problem 20**

</div>

---

## Table of Contents

1. [Abstract](#1-abstract)
2. [Objectives](#2-objectives)
3. [Background](#3-background)
4. [System Architecture](#4-system-architecture)
5. [Experimental Setup](#5-experimental-setup)
6. [Methodology](#6-methodology)
7. [Results](#7-results)
8. [Dashboard Evidence](#8-dashboard-evidence)
9. [Analysis and Discussion](#9-analysis-and-discussion)
10. [Challenges and Resolutions](#10-challenges-and-resolutions)
11. [Conclusion](#11-conclusion)
12. [Repository Structure](#12-repository-structure)

---

## 1. Abstract

This experiment sets up resource monitoring on a Kubernetes cluster using Metrics Server and the Kubernetes Dashboard. A containerized PHP application is deployed on a Minikube cluster and subjected to a synthetic HTTP load. CPU and memory usage of the application pod and the cluster node are recorded before, during, and after the load using `kubectl top` and the Dashboard. The results show a sharp and reversible increase in CPU usage under load, stable memory usage, and consistent readings across both monitoring tools.

## 2. Objectives

- Install and configure Metrics Server and the Kubernetes Dashboard.
- Deploy a sample application on the cluster.
- Generate load and observe the change in pod and node CPU and memory usage.
- Verify that `kubectl top` and the Dashboard report consistent values.

## 3. Background

Monitoring shows how workloads consume cluster resources. This information is needed to tune resource requests and limits, size nodes correctly, diagnose performance problems, and support autoscaling.

| Concept | Description |
|---|---|
| Metrics Server | Cluster add-on that collects CPU and memory usage from each node's kubelet and exposes it through the Metrics API. It holds current values only, not history. |
| kubectl top | Command-line tool that reads the Metrics API and displays current node and pod usage. |
| Kubernetes Dashboard | Web interface that shows workloads, nodes, and resource usage graphs. |
| Deployment and Pod | The Deployment maintains the desired number of application Pods. |
| NodePort Service | Exposes the application outside the cluster. |
| Units | CPU is measured in millicores (1000m = 1 core). Memory is measured in MiB. |

## 4. System Architecture

```
                 +----------------------- Minikube Cluster -----------------------+
                 |                                                                 |
 Load Generator  |  +-----------+     +---------------+     +----------------+     |
 (BusyBox pod) --+->| Service   | --> | php-app Pod   |     | Metrics Server |<----+-- kubelet
                 |  | (NodePort)|     | (PHP + Apache)|     +-------+--------+     |
                 |  +-----------+     +---------------+             |              |
                 |                                                  v              |
                 |                                   Metrics API (metrics.k8s.io)  |
                 +--------------------------------------------------+--------------+
                                                                    |
                                            +-----------------------+----------+
                                            |                                  |
                                       kubectl top                  Kubernetes Dashboard
```

## 5. Experimental Setup

| Component | Detail |
|---|---|
| Host environment | Ubuntu 24.04 on WSL 2 (Windows) |
| Container runtime | Docker |
| Cluster | Minikube v1.39.0 (Docker driver), Kubernetes v1.37.0 |
| Node capacity | 8 CPU cores, 15.38 GiB memory |
| Application | PHP 8.3 with Apache (`php:8.3-apache` base image) |
| Metrics collection | Metrics Server v0.9.0 (Minikube addon, 60 s resolution) |
| Visualization | Kubernetes Dashboard v2.7.0 (Minikube addon) |
| Load generator | BusyBox 1.28 pod issuing continuous HTTP requests |

## 6. Methodology

**Step 1. Build the application image**
```bash
docker build -t php-monitor:latest .
```

**Step 2. Start the cluster and load the image**
```bash
minikube start --driver=docker
minikube image load php-monitor:latest
```

**Step 3. Deploy the application and expose it**
```bash
kubectl apply -f deployment.yaml
kubectl expose deployment php-app --type=NodePort --port=80
kubectl get pods
kubectl get svc php-app
```

**Step 4. Enable monitoring**
```bash
minikube addons enable metrics-server
kubectl get apiservice v1beta1.metrics.k8s.io    # AVAILABLE must be True
minikube dashboard --url
```

**Step 5. Record baseline usage**
```bash
kubectl top nodes
kubectl top pods
```

**Step 6. Generate load**
```bash
kubectl run load-gen --image=busybox:1.28 --restart=Never -- \
  /bin/sh -c "while true; do wget -q -O- http://php-app; done"
```

**Step 7. Record usage under load**
```bash
kubectl top nodes
kubectl top pods
```

**Step 8. Remove the load and record recovery**
```bash
kubectl delete pod load-gen
kubectl top nodes
kubectl top pods
```

## 7. Results

Timestamped raw output is available in [`evidence.txt`](evidence.txt).

| Stage | Pod CPU | Pod Memory | Node CPU | Node Memory |
|---|---|---|---|---|
| Before load | 1m | 11 MiB | 258m | 638 MiB |
| Under load | 992m | 14 MiB | 1204m | 928 MiB |
| After load | 1m | 13 MiB | 318m | 929 MiB |

## 8. Dashboard Evidence

**Figure 1. Pod resource usage under load.** The php-app pod shows about 974m CPU and 14 MiB memory, matching the `kubectl top` reading.

![Pods under load](1-pods-under-load.png)

**Figure 2. Node CPU as the load begins.**

![Node load start](2-node-load-start.png)

**Figure 3. Node CPU at peak load (about 1.2 of 8 cores).**

![Node peak load](3-node-peak-load.png)

**Figure 4. Node CPU returning to baseline after the load is removed.**

![Node after load](4-node-after-load.png)

## 9. Analysis and Discussion

- **CPU:** Pod CPU rose from 1m to about 990m under load and returned to 1m afterwards. Node CPU rose from 258m to 1204m (about 15% of the 8 available cores) and recovered to 318m.
- **Memory:** Pod memory stayed between 11 and 14 MiB. The workload is CPU-bound and does not allocate additional memory under load.
- **Tool consistency:** `kubectl top` (992m) and the Dashboard (974m) agree. The small difference is due to different sampling times.
- **Metric latency:** Metrics Server scrapes every 60 seconds, so readings lag the real load by up to one minute. After the load was removed, the pod reading took about a minute to fall.

## 10. Challenges and Resolutions

| Issue | Resolution |
|---|---|
| `permission denied` on the Docker socket | Added the user to the `docker` group with `sudo usermod -aG docker $USER`. |
| Minikube refused to run as root | Ran `minikube start` as a normal user instead of using `sudo`. |
| `ErrImagePull` / `ImagePullBackOff` for the local image | Loaded the image with `minikube image load` and set `imagePullPolicy: IfNotPresent` in the Deployment. |
| `Metrics API not available` immediately after enabling Metrics Server | Waited for the metrics-server pod to become Ready and the APIService to report `True`. |

## 11. Conclusion

Metrics Server and the Kubernetes Dashboard were successfully configured on a Minikube cluster. Application load produced a clear and reversible increase in pod and node CPU usage, while memory remained stable. The agreement between `kubectl top` and the Dashboard confirms that the monitoring setup is accurate and suitable for tuning resource requests and limits and for diagnosing workload problems.

## 12. Repository Structure

```
.
├── index.php                  # Sample PHP application
├── Dockerfile                 # Container image definition
├── deployment.yaml            # Kubernetes Deployment manifest
├── evidence.txt               # Timestamped kubectl top output
├── 1-pods-under-load.png      # Dashboard: pods under load
├── 2-node-load-start.png      # Dashboard: node, load starting
├── 3-node-peak-load.png       # Dashboard: node, peak load
├── 4-node-after-load.png      # Dashboard: node, after load
└── README.md
```
